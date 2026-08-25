<?php
/**
 * Ruta: /app/Controllers/AuthController.php
 * Login del personal interno. Los clientes no inician sesión (spec §10).
 */

namespace App\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Role;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if (Auth::check()) {
            return $this->redirect(Role::homeFor(Auth::role()));
        }

        return $this->view('auth.login', ['title' => 'Ingresar']);
    }

    public function login(): Response
    {
        $this->verifyCsrf();

        $validator = new Validator($this->request->all(), [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors(), 'Revisa tus credenciales.');
        }

        $email    = (string) $this->request->input('email');
        $password = (string) $this->request->input('password');

        if (Auth::isLocked($email)) {
            $minutes = (int) ceil(Auth::lockSecondsLeft($email) / 60);
            Session::flash('error', "Demasiados intentos fallidos. Vuelve a intentarlo en {$minutes} minuto(s).");

            return $this->back('/login');
        }

        if (!Auth::attempt($email, $password, $this->request->ip())) {
            ActivityLogger::log('auth.failed', 'user', null, 'Intento fallido para ' . $email);
            Session::flash('error', 'Email o contraseña incorrectos.');
            Session::flashInput(['email' => $email]);

            return $this->back('/login');
        }

        ActivityLogger::log('auth.login', 'user', Auth::id(), 'Inicio de sesión');

        $user = Auth::user();

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            Session::flash('info', 'Por seguridad, define una contraseña nueva antes de continuar.');

            return $this->redirect('/cuenta/password');
        }

        $intended = Session::get('_intended_url');
        Session::forget('_intended_url');

        Session::flash('success', '¡Hola ' . $user['first_name'] . '!');

        return $this->redirect($intended ?: Role::homeFor(Auth::role()));
    }

    public function logout(): Response
    {
        if (Auth::check()) {
            ActivityLogger::log('auth.logout', 'user', Auth::id(), 'Cierre de sesión');
        }

        Auth::logout();
        Session::start();
        Session::flash('success', 'Cerraste sesión correctamente.');

        return $this->redirect('/login');
    }

    // -----------------------------------------------------------------
    //  Perfil propio
    // -----------------------------------------------------------------

    public function profile(): Response
    {
        return $this->view('auth.profile', [
            'title' => 'Mi cuenta',
            'user'  => Auth::user(),
        ]);
    }

    public function updateProfile(): Response
    {
        $this->verifyCsrf();

        $userId = (int) Auth::id();

        $data = $this->validate([
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'phone'      => 'nullable|phone',
            'email'      => 'required|email|max:150|unique:users,email,' . $userId,
        ]);

        (new User())->update($userId, $data);
        ActivityLogger::log('user.updated', 'user', $userId, 'Actualizó su perfil');

        return $this->redirectWith('/cuenta', 'Perfil actualizado.');
    }

    public function showChangePassword(): Response
    {
        return $this->view('auth.password', [
            'title'  => 'Cambiar contraseña',
            'forced' => (int) (Auth::user()['must_change_password'] ?? 0) === 1,
        ]);
    }

    public function changePassword(): Response
    {
        $this->verifyCsrf();

        $user   = Auth::user();
        $forced = (int) ($user['must_change_password'] ?? 0) === 1;

        $rules = [
            'password' => 'required|min:8|max:100|confirmed',
        ];

        // En el cambio forzado del primer ingreso no se pide la anterior.
        if (!$forced) {
            $rules['current_password'] = 'required';
        }

        $validator = new Validator($this->request->all(), $rules, [
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        if (!$forced && !Auth::confirmPassword((string) $this->request->input('current_password'))) {
            return $this->backWithErrors(['current_password' => ['La contraseña actual no es correcta.']]);
        }

        $password = (string) $this->request->input('password');

        if ($this->isWeak($password)) {
            return $this->backWithErrors([
                'password' => ['Usa una contraseña más segura: combina mayúsculas, minúsculas y números.'],
            ]);
        }

        (new User())->updatePassword((int) $user['id'], $password);
        ActivityLogger::log('user.password_changed', 'user', (int) $user['id'], 'Cambió su contraseña');

        Session::flash('success', 'Contraseña actualizada.');

        return $this->redirect(Role::homeFor(Auth::role()));
    }

    // -----------------------------------------------------------------
    //  Recuperación (arquitectura lista; el envío real llega con el email)
    // -----------------------------------------------------------------

    public function showForgot(): Response
    {
        return $this->view('auth.forgot', ['title' => 'Recuperar acceso']);
    }

    public function sendReset(): Response
    {
        $this->verifyCsrf();

        $email = (string) $this->request->input('email');
        $user  = (new User())->findByEmail($email);

        if ($user !== null && (int) $user['status'] === 1) {
            $token = bin2hex(random_bytes(32));
            (new User())->storeResetToken((int) $user['id'], $token);

            $link = url('restablecer/' . $token);

            // Etapa 2: enviar por email. Por ahora queda en el log técnico.
            logger()->info('Enlace de recuperación generado', ['user_id' => $user['id']]);

            if (config('app.debug')) {
                Session::flash('info', 'Enlace de recuperación (modo desarrollo): ' . $link);
            }
        }

        // Respuesta idéntica exista o no la cuenta: no se filtra información.
        return $this->redirectWith(
            '/recuperar',
            'Si el email está registrado, enviaremos las instrucciones para restablecer la contraseña.',
            'info'
        );
    }

    public function showReset(string $token): Response
    {
        $user = (new User())->findByResetToken($token);

        if ($user === null) {
            Session::flash('error', 'El enlace expiró o no es válido.');

            return $this->redirect('/recuperar');
        }

        return $this->view('auth.reset', ['title' => 'Nueva contraseña', 'token' => $token]);
    }

    public function resetPassword(string $token): Response
    {
        $this->verifyCsrf();

        $user = (new User())->findByResetToken($token);

        if ($user === null) {
            Session::flash('error', 'El enlace expiró o no es válido.');

            return $this->redirect('/recuperar');
        }

        $validator = new Validator($this->request->all(), [
            'password' => 'required|min:8|max:100|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        (new User())->updatePassword((int) $user['id'], (string) $this->request->input('password'));
        ActivityLogger::log('user.password_reset', 'user', (int) $user['id'], 'Restableció su contraseña');

        return $this->redirectWith('/login', 'Contraseña actualizada. Ya puedes ingresar.');
    }

    /** Rechaza contraseñas obvias sin frustrar al usuario con reglas absurdas. */
    private function isWeak(string $password): bool
    {
        $common = ['12345678', 'password', 'contrasena', 'flava2026!', 'qwertyui', 'barberia'];

        if (in_array(mb_strtolower($password), $common, true)) {
            return true;
        }

        return !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password);
    }
}
