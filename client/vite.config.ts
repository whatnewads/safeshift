import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  // Load env file based on `mode` in the current working directory.
  const env = loadEnv(mode, process.cwd(), '');
  
  // API target configuration:
  // - Windows (both PHP and npm in Windows): use 'http://localhost:8000' (default)
  // - WSL (npm in WSL, PHP in Windows): set VITE_API_HOST to Windows host IP
  //   To find Windows host IP from WSL: cat /etc/resolv.conf | grep nameserver | awk '{print $2}'
  //   Usually something like 'http://172.x.x.x:8000'
  const apiTarget = env.VITE_API_HOST || 'http://localhost:8000';
  
  return {
    plugins: [tailwindcss(), react()],
    
    // Path aliases
    resolve: {
      alias: {
        '@': '/src',
        '@app': '/src/app',
        '@components': '/src/app/components',
        '@pages': '/src/app/pages',
        '@hooks': '/src/app/hooks',
        '@services': '/src/app/services',
        '@contexts': '/src/app/contexts',
        '@types': '/src/app/types',
        '@utils': '/src/app/utils',
      },
    },
    
    // Development server configuration
    server: {
      port: 5173,
      host: true,
      // Proxy API requests to PHP backend during development
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
          // Fix cookie domain rewriting for session persistence
          // Rewrite cookie domain from backend (localhost:8000) to match Vite dev server
          cookieDomainRewrite: 'localhost',
          // Ensure cookie path allows all routes
          cookiePathRewrite: {
            '*': '/',
          },
          // Configure proxy with debug logging for cookies
          configure: (proxy, _options) => {
            proxy.on('error', (err, _req, _res) => {
              console.log('[Vite Proxy Error]', err.message);
            });
            proxy.on('proxyReq', (proxyReq, req, _res) => {
              // Log outgoing request with cookie info
              const cookie = req.headers.cookie || '(no cookie)';
              console.log(`[Vite Proxy] → ${req.method} ${req.url}`);
              console.log(`[Vite Proxy]   Cookie sent: ${cookie.substring(0, 80)}${cookie.length > 80 ? '...' : ''}`);
            });
            proxy.on('proxyRes', (proxyRes, req, _res) => {
              // Log response with set-cookie info
              const setCookie = proxyRes.headers['set-cookie'];
              console.log(`[Vite Proxy] ← ${proxyRes.statusCode} ${req.url}`);
              if (setCookie) {
                console.log(`[Vite Proxy]   Set-Cookie: ${JSON.stringify(setCookie).substring(0, 100)}`);
              }
            });
          },
        },
      },
    },
    
    // Build configuration
    build: {
      outDir: 'dist', // Build to project root /dist
      emptyOutDir: true,
      sourcemap: true,
      rollupOptions: {
        output: {
          manualChunks: {
            vendor: ['react', 'react-dom', 'react-router-dom'],
            ui: ['@radix-ui/react-dialog', '@radix-ui/react-dropdown-menu'],
          },
        },
      },
    },
    
    // Environment variable prefix
    envPrefix: 'VITE_',
  };
});
