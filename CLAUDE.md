# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**进销存及CRM · AI 助手后端** — 面向中小企业的 ERP+CRM 一体化 AI 管理系统。

- **Backend**: Laravel 12 API (PHP 8.4) + Filament v5 Admin — `backend/`
- **WeChat Mini Program Shell**: 原生小程序（ES5 风格）— `miniprogram/`（行业选择壳，AI 消息走 `/api/ai/message`）

> miniapp-shell2 是 miniapp-shell 的分叉版本，保留了完整的小程序壳基础设施（行业/菜单模版/知识库），并移植了 app62 的 ERP+CRM 业务域。

---

## 端口 & 数据库

| Service | Port | DB | PM2 进程名 |
|---------|------|-----|-----------|
| Laravel API + Filament Admin | **8305** | **miniapptemplate** | `app-miniapp2-backend` |

### PM2 管理（必须加 sudo -u mi）
```bash
sudo -u mi pm2 list
sudo -u mi pm2 restart app-miniapp2-backend
sudo -u mi pm2 logs app-miniapp2-backend --lines 50
sudo -u mi pm2 save
```

### 数据库连接
- Host: `127.0.0.1:5432`
- DB / User / Password: `miniapptemplate` / `miniapp2` / `miniapp2_Xk2026`
- pgvector 已安装（knowledge_items.embedding 列）

---

## 核心业务逻辑（ERP + CRM）

### 进销存（ERP）

| 模块 | 表 |
|------|---|
| 商品分类 | `product_categories` |
| 供应商 | `suppliers` |
| 商品（含库存量） | `products`（`stock_quantity` 实时滚动） |
| 进货单 | `purchase_orders` + `purchase_order_items` |
| 销售单 | `sales_orders` + `sales_order_items` |
| 库存流水 | `inventory_logs`（每次进/出货写一条） |

**库存变动写链（以进货为例）：**
```
POST /api/ai/message → intent: purchase_add
  → PurchaseOrderService::create()
      ├─ ProductService::findOrCreate(product_name)
      ├─ PurchaseOrder + PurchaseOrderItems 创建
      └─ product.stock_quantity += quantity
         inventory_logs 写流水
```

### CRM

| 模块 | 表 |
|------|---|
| 客户（含画像） | `customers`（gender / age_group / city / industry / income_level / hobbies / tags） |
| 联系人 | `contacts` |
| 线索 | `leads` |
| 商机 | `opportunities` |
| 跟进记录 | `activities` |

### 知识库（RAG）

| 表 | 用途 |
|---|---|
| `knowledge_categories` | 知识分类 |
| `knowledge_items` | 知识条目（含 `embedding vector(1536)`） |

AI 消息处理前，`KnowledgeService::findRelevant($text)` 做相似度检索，将匹配结果拼入 system prompt 末尾。

---

## 开发命令

### Backend（从 `backend/` 目录运行）
```bash
php artisan serve --host=0.0.0.0 --port=8305

php artisan migrate --force
php artisan config:clear          # .env 变更后必须执行
php artisan route:list --path=api

php artisan make:model Foo -mf
php artisan make:controller Api/FooController
```

---

## 认证系统

三条认证路径，均由 `auth.hybrid` 中间件（`JwtOrSanctumAuth`）按 token 形状路由：

| Token 形状 | 路径 | 说明 |
|-----------|------|-----|
| `ak_` 开头 | API Key 路径 | 查 `api_keys` 表，`last_used_at` 自动更新 |
| 带两个点（JWT） | JWT 路径 | HS256 验签，claims 含 `sub`/`store_id` |
| 其他 | Sanctum 路径 | Sanctum opaque token |

**管理后台账号**: `admin@sjtxg.com` / `Admin@2026`

**API Key 管理**:
- `GET /api/auth/api-keys` — 列出当前用户的 key
- `POST /api/auth/api-keys` — 创建新 key（返回明文，仅显示一次）
- `DELETE /api/auth/api-keys/{id}` — 吊销

---

## AI 助手架构

### 处理流程

```
POST /api/ai/message  { text, image_base64?, session_id? }
  ↓
AiAssistantController::message()
  ├─ KnowledgeService::findRelevant($text)  → knowledgeContext（RAG）
  ├─ resolveTemplateSettings()               → settingsOverride（MenuTemplate）
  ├─ AiService::parseInventoryIntent($text, $imageBase64, $knowledgeContext, $settingsOverride)
  │     → DeepSeek / vision / whisper 三路
  │     → 返回 { intent, keyword, reply, purchase?, customer?, ... }
  │     → JSON 解析失败自动重试一次（flash 模型偶发截断）
  ├─ dispatchIntent($intent, $parsed)        → 43-intent match → 调 service → 返回 reply
  ├─ AiSession + AiMessage 存档
  └─ return { reply, intent, session_id }
```

### 43 意图枚举

**查询类**（直接查 DB）：
`product_query`, `purchase_query`, `sales_query`, `inventory_query`,
`supplier_query`, `category_query`,
`customer_query`, `customer_profile_query`, `lead_query`, `opportunity_query`,
`activity_query`, `contact_query`, `overview_query`

**新增类**：
`product_add`, `purchase_add`, `sales_add`, `supplier_add`, `category_add`,
`customer_add`, `lead_add`, `opportunity_add`, `activity_add`, `contact_add`

**修改类**：
`product_update`, `purchase_update`, `sales_update`, `stock_update`, `supplier_update`,
`customer_update`, `lead_update`, `opportunity_update`, `activity_update`, `contact_update`

**删除类**：
`product_delete`, `purchase_delete`, `sales_delete`, `supplier_delete`, `category_delete`,
`customer_delete`, `lead_delete`, `opportunity_delete`, `activity_delete`, `contact_delete`

**个人助理**：`assistant_note`, `ai_suggestion`

**兜底**：`other` → 返回 `reply` 字段原文

### AI 模型配置

| 用途 | 环境变量 | 当前值 |
|------|---------|-------|
| 文字意图解析 | `AI_BASE_URL` / `AI_MODEL` | DeepSeek `deepseek-v4-flash` |
| 图片识别 | `AI_VISION_BASE_URL` / `AI_VISION_MODEL` | 未配置 |
| 语音转文字 | `AI_WHISPER_BASE_URL` / `AI_WHISPER_MODEL` | 未配置 |

⚠️ `max_tokens=3000` + 一次重试：flash 模型偶发返回截断 JSON，`extractJson()` 抠 JSON 后 `json_decode` 失败 → 自动重试一次，两次均失败才返回「没太理解」。

---

## Services 层

每个域实体对应一个 Service，Controller 保持薄：

| Service | 职责 |
|---------|------|
| `ProductService` | 商品 CRUD + `findOrCreate` + `setStock` + `adjustStock` |
| `SupplierService` | 供应商 CRUD + `findByKeyword` |
| `PurchaseOrderService` | 进货单创建（自动建商品、更新库存、写流水） |
| `SalesOrderService` | 销售单创建（扣减库存、写流水） |
| `CustomerService` | 客户 CRUD + `queryByProfile`（画像筛选） |
| `LeadService` | 线索 CRUD + `findByKeyword` |
| `OpportunityService` | 商机 CRUD + `findByKeyword` |
| `ActivityService` | 跟进记录 CRUD + `findByKeyword` + `mark_completed` |
| `ContactService` | 联系人 CRUD + `findByKeyword(name, customerKeyword?)` |
| `AiService` | LLM 三路调用（文字/图片/语音）+ `parseInventoryIntent` |
| `KnowledgeService` | pgvector 相似度检索 + `formatContext` |
| `JwtService` | JWT 签发/验证（HS256） |
| `EmbeddingService` | 向量化文本（写 knowledge_items.embedding） |

---

## Filament Admin（v5）

- **Panel**: `http://0.0.0.0:8305/admin`
- Resources 自动发现自 `backend/app/Filament/Resources/`
- 导航分组：

| 导航组 | 资源 |
|-------|------|
| **进销存** | `ProductCategoryResource`, `SupplierResource`, `ProductResource`, `PurchaseOrderResource`, `SalesOrderResource` |
| **CRM** | `CustomerResource`, `LeadResource`, `OpportunityResource`, `ActivityResource` |
| **系统** | `UserResource`, `IndustryResource`, `MenuTemplateResource`, `QuickActionResource`, `ApiKeyResource`, `AppSettingResource` |
| **知识库** | `KnowledgeCategoryResource`, `KnowledgeItemResource` |

资源组织方式：大部分资源使用目录式（`Products/ProductResource.php`），命名空间为 `App\Filament\Resources\Products\ProductResource`。

---

## API 端点

### 认证
| Method | Endpoint | Notes |
|--------|----------|-------|
| POST | `/api/login` | `{login, password}` → `{token, jwt_token, store_id, user}` |
| GET | `/api/me` | 当前用户 |

### AI 助手
| Method | Endpoint | Notes |
|--------|----------|-------|
| POST | `/api/ai/message` | `{text, image_base64?, session_id?}` → `{reply, intent, session_id}` |
| POST | `/api/ai/voice` | multipart `audio` → 同 message 流程 |
| GET | `/api/ai/sessions` | 会话历史列表 |
| GET | `/api/ai/sessions/{id}/messages` | 单个会话消息 |

### ERP（只读）
| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/api/products` | 商品列表 |
| GET | `/api/products/{id}` | 商品详情 |
| GET | `/api/purchase-orders` | 进货单列表 |
| GET | `/api/sales-orders` | 销售单列表 |

### CRM（完整 CRUD）
| 资源 | 路径前缀 |
|------|---------|
| 客户 | `/api/customers` |
| 线索 | `/api/leads` |
| 商机 | `/api/opportunities` |
| 跟进 | `/api/activities` |
| 联系人 | `/api/contacts` |

### API Key
| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/api/auth/api-keys` | 列出 key |
| POST | `/api/auth/api-keys` | 创建 key |
| DELETE | `/api/auth/api-keys/{id}` | 吊销 key |

### 行业 / 小程序壳
| Method | Endpoint | Notes |
|--------|----------|-------|
| GET | `/api/industries` | 公开，返回行业列表（含 `api_base`/`api_token`） |
| GET | `/api/quick-actions` | `?industry=slug` 返回当前生效模版按钮 |

---

## 微信小程序壳（`miniprogram/`）

原生小程序，ES5 风格。页面：`chat`（聊天）、`report`（报表）。

**关键配置**（`miniprogram/app.js`）：
- `apiBaseUrl`: `https://app55.xingke888.com/api`

**AppID**（`miniprogram/project.config.json`）：
- 开发期使用 `touristappid`，发布前需替换为正式 AppID

本项目小程序直接连接本后端，无行业跳转逻辑：
- 所有请求走 `https://app55.xingke888.com/api`
- AI 消息：`POST /api/ai/message`

---

## Laravel 12 约定

- 无 `app/Http/Kernel.php`，中间件注册在 `bootstrap/app.php`
- Model 用 `casts()` 方法（非 `$casts` 属性）
- 验证用 Form Request 类
- 配置读取用 `config('key')`，不用 `env()`（除 config 文件内部）

## 关键配置文件

- `backend/.env` — DB、AI keys、JWT_SECRET、APP_URL（含端口 8305）
- `backend/config/ai.php` — AI 三路配置
- `backend/config/jwt.php` — HS256 secret
