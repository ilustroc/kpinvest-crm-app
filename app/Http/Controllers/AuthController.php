<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller{
  public function form(){ 
    return view('auth.login'); 
  }
  public function login(Request $r)
  {
      $data = $r->validate([
          'email'    => ['required','email'],
          'password' => ['required'],
      ]);
  
      // Si el correo existe pero está inactivo, avisa antes de intentar
      $user = User::where('email', $data['email'])->first();
      if ($user && !$user->active) {
          return back()
              ->withErrors(['email' => 'Tu cuenta está inactiva. Contacta a Soporte.'])
              ->onlyInput('email');
      }
  
      // Exigir active=1 en el attempt (cierra el paso a inactivos)
      $remember = $r->boolean('remember');
      if (Auth::attempt([
          'email' => $data['email'],
          'password' => $data['password'],
          'active' => 1, // <- clave
      ], $remember)) {
          $r->session()->regenerate();
          return redirect()->intended(route('panel'));
      }
  
      return back()
          ->withErrors(['email' => 'Credenciales inválidas'])
          ->onlyInput('email');
  }  

  public function logout(Request $r){ 
    Auth::logout(); $r->session()->invalidate(); 
    $r->session()->regenerateToken(); return redirect()->route('login'); 
  }
}