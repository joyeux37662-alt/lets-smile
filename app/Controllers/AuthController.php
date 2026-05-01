<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\User;
use Throwable;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Connexion au cabinet'], 'layouts/auth');
    }

    public function login(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            flash('error', 'Session expirée. Veuillez réessayer.');
            $this->redirect('/login');
        }

        $cabinet = trim((string) ($_POST['cabinet'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        remember_old($_POST, ['cabinet', 'email']);

        if ($cabinet === '' || $email === '' || $password === '') {
            flash('error', 'Veuillez renseigner le cabinet, l’email et le mot de passe.');
            $this->redirect('/login');
        }

        try {
            $user = (new User())->findForLogin($cabinet, $email);
        } catch (Throwable) {
            flash('error', 'Impossible de joindre la base de données. Vérifiez Laragon, MySQL et le fichier .env.');
            $this->redirect('/login');
        }

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            flash('error', 'Identifiants incorrects.');
            $this->redirect('/login');
        }

        Auth::login($user);
        (new User())->markLoggedIn((int) $user['id']);
        flash('success', 'Bienvenue sur Let’s Smile.');
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            $this->redirect('/dashboard');
        }

        Auth::logout();
        redirect('/login');
    }
}
