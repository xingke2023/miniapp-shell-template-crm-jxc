module.exports = {
  apps: [
    {
      name: 'app-miniapp2-backend',
      script: 'php',
      args: 'artisan serve --host=0.0.0.0 --port=8305',
      cwd: '/home/ubuntu/mini-shell-template/backend',
      interpreter: 'none',
      env: {
        APP_ENV: 'production',
      },
      error_file: '/home/ubuntu/mini-shell-template/logs/pm2-err.log',
      out_file: '/home/ubuntu/mini-shell-template/logs/pm2-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      restart_delay: 3000,
      max_restarts: 10,
    }
  ]
};
