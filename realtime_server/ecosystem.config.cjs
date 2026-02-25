module.exports = {
  apps: [
    {
      name: 'elms-realtime',
      script: './server.js',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      max_memory_restart: '300M',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
        SOCKET_PATH: '/socket.io',
        CORS_ORIGIN: '*',
        TRUST_PROXY: 1,
        PING_TIMEOUT_MS: 30000,
        PING_INTERVAL_MS: 25000
      }
    }
  ]
};
