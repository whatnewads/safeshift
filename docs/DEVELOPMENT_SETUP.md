# Development Setup Guide

## WSL/Windows Network Configuration

This project requires running both PHP (backend) and npm/Vite (frontend) development servers. There are two common configurations:

### Option 1: Run Everything in WSL (Recommended)

Run both PHP and npm in WSL for simplest networking:

```bash
# In WSL Terminal 1 - Start PHP server
cd /mnt/c/Users/wesyi/bckup/project
php -S localhost:8000 router.php

# In WSL Terminal 2 - Start Vite dev server
cd /mnt/c/Users/wesyi/bckup/project
npm run dev
```

With this setup, `localhost` works correctly since both servers are in the same network namespace.

### Option 2: PHP in Windows, npm in WSL (Current Setup)

If PHP runs in Windows PowerShell and npm runs in WSL, you need to configure the Vite proxy to reach the Windows host.

#### Step 1: Find Windows Host IP from WSL

Run this command in your WSL terminal:

```bash
cat /etc/resolv.conf | grep nameserver | awk '{print $2}'
```

This will output something like: `172.22.192.1`

#### Step 2: Set the Environment Variable

Create or edit `.env` file in the project root:

```bash
# .env
VITE_API_HOST=http://172.22.192.1:8000
```

Or set it inline when running npm:

```bash
VITE_API_HOST=http://172.22.192.1:8000 npm run dev
```

#### Step 3: Start the servers

**Windows PowerShell:**
```powershell
php -S localhost:8000 router.php
```

**WSL Terminal:**
```bash
npm run dev
```

### Option 3: Install Node.js in Windows

If you prefer to run everything in Windows:

1. Download Node.js from [nodejs.org](https://nodejs.org/)
2. Install Node.js (LTS version recommended)
3. Restart PowerShell
4. Run both servers in Windows PowerShell:

**Terminal 1:**
```powershell
php -S localhost:8000 router.php
```

**Terminal 2:**
```powershell
npm run dev
```

## Troubleshooting

# Terminal 1 - WSL
cd /mnt/c/Users/wesyi/bckup/project
php -S localhost:8000 router.php

# Terminal 2 - WSL
cd /mnt/c/Users/wesyi/bckup/project
npm run dev


### Error: ECONNREFUSED 127.0.0.1:8000

This error occurs when:
- npm runs in WSL trying to connect to `localhost:8000`
- PHP runs in Windows on `localhost:8000`
- WSL and Windows have separate network stacks

**Solution:** Use Option 1, 2, or 3 above.

### Verifying PHP Server is Running

```bash
# From Windows PowerShell
curl http://localhost:8000/api/v1/health

# From WSL (if using Option 2 with Windows host IP)
curl http://172.22.192.1:8000/api/v1/health
```

### Finding the Correct Windows Host IP

Alternative methods to find the Windows host IP from WSL:

```bash
# Method 1: From resolv.conf
cat /etc/resolv.conf | grep nameserver

# Method 2: Using hostname
ping -c 1 $(hostname).local

# Method 3: If Docker Desktop is installed
# Use host.docker.internal (may not always work)
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `VITE_API_HOST` | PHP backend URL for Vite proxy | `http://localhost:8000` |
| `VITE_API_URL` | API URL for production builds | (empty - uses proxy) |
| `VITE_APP_NAME` | Application display name | `SafeShift EHR` |
| `VITE_SESSION_TIMEOUT` | Session timeout in seconds | `3600` |
| `VITE_SESSION_WARNING` | Warning before timeout (seconds) | `300` |
| `VITE_ENV` | Environment mode | `development` |
