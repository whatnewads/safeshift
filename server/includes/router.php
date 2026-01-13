<?php
/**
 * DEPRECATED: This file is deprecated as of November 2025
 *
 * All routing functionality has been consolidated into /index.php
 * This file is kept for backward compatibility only.
 *
 * Please update any references to use the main router in /index.php
 *
 * @deprecated since November 2025
 * @see /index.php for the new routing implementation
 */

namespace App\route;
use function App\auth\{login_start,login_complete,require_login,current_user,redirect_after_login,logout};
use App\log;

// Log deprecation warning when this file is used
trigger_error('router.php is deprecated. Please use /index.php for routing.', E_USER_DEPRECATED);

function render(string $tpl, array $vars=[]): string {
    extract($vars);
    ob_start();
    include __DIR__."/../templates/{$tpl}.php";
    return ob_get_clean();
}

function dispatch(string $path): string {
    if ($path === '/' || $path === '/login') {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $u = trim($_POST['username']??''); $p = $_POST['password']??'';
            $res = login_start($u,$p);
            if ($res['ok'] && $res['stage']==='otp') {
                return render('verify', ['message'=>$res['msg']]);
            }
            return render('login', ['error'=>$res['msg']??null]);
        }
        return render('login');
    }

    if ($path === '/verify' && $_SERVER['REQUEST_METHOD']==='POST') {
        $code = trim($_POST['code']??'');
        $res = login_complete($code);
        if ($res['ok']) {
            $u = current_user();
            redirect_after_login($u['user_id']);
        }
        return render('verify', ['error'=>$res['msg']??null]);
    }

    if ($path === '/logout') { logout(); }

    if ($path === '/dashboard' || str_starts_with($path, '/dashboard/')) {
        require_login();
        if ($path==='/dashboard/admin')      return render('dashboard_admin');
        if ($path==='/dashboard/employer')   return render('dashboard_employer');
        if ($path==='/dashboard/clinician')  return render('dashboard_clinician');
        return render('dashboard_default');
    }

    http_response_code(404);
    return "Not Found";
}
