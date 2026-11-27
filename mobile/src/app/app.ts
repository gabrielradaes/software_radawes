import { Component, computed, inject, signal } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { SwUpdate } from '@angular/service-worker';
import { filter } from 'rxjs';
import { Session } from './core/session';
import { TextSize } from './core/text-size';

/** Marco de la app: barra de arriba (tienda) y pestañas abajo, como una app del celular. */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    @if (showChrome()) {
      <header class="topbar">
        <div class="brand"><span class="logo">RW</span><div><b>RadaWes</b><small>{{ session.user()?.first_name }} · {{ session.user()?.role }}</small></div></div>
        <button class="aa" (click)="text.next(); flash()" [attr.aria-label]="'Tamaño de letra: ' + text.label">Aa</button>
        @if (!session.user()?.scoped && (session.me()?.branches?.length ?? 0) > 1) {
          <select class="branch" [value]="session.branchId()" (change)="session.chooseBranch(+$any($event.target).value)" aria-label="Tienda">
            @for (b of session.me()?.branches; track b.id) { <option [value]="b.id" [selected]="b.id === session.branchId()">{{ b.name }}</option> }
          </select>
        } @else {
          <span class="branch-fixed">📍 {{ session.branchName() }}</span>
        }
      </header>
    }
    @if (sizeNote()) { <p class="toast">🔠 Letra: {{ sizeNote() }}</p> }
    @if (updateReady()) {
      <button class="update" (click)="reload()">✨ Hay una versión nueva de la app. Toca para actualizar.</button>
    }
    <main [class.with-chrome]="showChrome()"><router-outlet /></main>
    @if (showChrome()) {
      <nav class="tabs">
        @if (session.can().stock) { <a routerLink="/tallas" routerLinkActive="on"><i>🔎</i>Tallas</a> }
        @if (session.can().sell) { <a routerLink="/vender" routerLinkActive="on"><i>🧾</i>Vender</a> }
        @if (session.can().entry) { <a routerLink="/ingresar" routerLinkActive="on"><i>📦</i>Ingresar</a> }
        <a routerLink="/asistente" routerLinkActive="on"><i>🎙️</i>Asistente</a>
        <a routerLink="/hoy" routerLinkActive="on"><i>📅</i>Mi día</a>
      </nav>
    }
  `,
})
export class App {
  protected session = inject(Session);
  protected text = inject(TextSize);   // se aplica apenas abre la app
  protected sizeNote = signal('');
  private router = inject(Router);
  private url = signal('');
  protected updateReady = signal(false);
  protected showChrome = computed(() => !!this.session.user() && !this.url().startsWith('/entrar'));

  constructor() {
    this.router.events.pipe(filter((e) => e instanceof NavigationEnd)).subscribe((e) => this.url.set((e as NavigationEnd).urlAfterRedirects));
    const sw = inject(SwUpdate);
    if (sw.isEnabled) {
      sw.versionUpdates.pipe(filter((e) => e.type === 'VERSION_READY')).subscribe(() => this.updateReady.set(true));
    }
  }

  reload() { location.reload(); }

  flash() {
    const l = this.text.label;
    this.sizeNote.set(l);
    setTimeout(() => { if (this.sizeNote() === l) this.sizeNote.set(''); }, 1500);
  }
}
