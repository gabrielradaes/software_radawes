import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Session } from '../core/session';
import { errorText } from '../core/api';
import { TextSize } from '../core/text-size';

@Component({
  selector: 'page-login',
  imports: [FormsModule],
  template: `
    <div class="login">
      <div class="login-card">
        <span class="logo big">RW</span>
        <h1>RadaWes Tiendas</h1>
        <p class="muted">Entra con el mismo correo y contraseña del panel.</p>
        <form (ngSubmit)="go()" autocomplete="on">
          <label>Correo
            <input type="email" name="email" [(ngModel)]="email" required autocomplete="username" inputmode="email" placeholder="tu.correo@gmail.com">
          </label>
          <label>Contraseña
            <div class="pass">
              <input [type]="show() ? 'text' : 'password'" name="password" [(ngModel)]="password" required autocomplete="current-password">
              <button type="button" class="ghost" (click)="show.set(!show())" [attr.aria-label]="show() ? 'Ocultar' : 'Mostrar'">{{ show() ? '🙈' : '👁️' }}</button>
            </div>
          </label>
          @if (error()) { <p class="error">{{ error() }}</p> }
          <button class="primary big" [disabled]="busy() || !email || !password">{{ busy() ? 'Entrando…' : 'Entrar' }}</button>
        </form>
        <p class="hint">¿Olvidaste tu contraseña? Pídela en el panel web: «¿Olvidaste tu contraseña?».</p>
        <button type="button" class="ghost" (click)="text.next()">🔠 Letra: {{ text.label }} (tocar para cambiar)</button>
        <p class="hint">💡 Para tenerla como app: en el menú del navegador toca <b>«Agregar a la pantalla de inicio»</b>.</p>
      </div>
    </div>
  `,
})
export class LoginPage {
  private session = inject(Session);
  protected text = inject(TextSize);
  private router = inject(Router);
  email = '';
  password = '';
  show = signal(false);
  busy = signal(false);
  error = signal('');

  constructor() {
    // Si ya hay sesión (o para traer la cookie de seguridad antes de entrar)
    this.session.load().then(() => { if (this.session.user()) this.router.navigateByUrl('/'); });
  }

  async go() {
    this.busy.set(true);
    this.error.set('');
    try {
      await this.session.login(this.email.trim(), this.password);
      this.router.navigateByUrl('/');
    } catch (e) {
      this.error.set(errorText(e));
      await this.session.load();   // por si la cookie de seguridad había vencido
    } finally {
      this.busy.set(false);
    }
  }
}
