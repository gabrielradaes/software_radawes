import { Component, inject, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { Api, errorText, isExpired } from '../core/api';
import { Session } from '../core/session';
import { TextSize } from '../core/text-size';

/** Mi día: lo que vendí hoy y lo último que hice (mi registro de actividad). */
@Component({
  selector: 'page-today',
  imports: [DecimalPipe],
  template: `
    <section class="page">
      <h1 class="page-title">📅 Mi día</h1>
      @if (error()) { <p class="error">{{ error() }}</p> }
      @if (data(); as d) {
        <div class="stats">
          <div><b>{{ d.sales.count }}</b><small>ventas</small></div>
          <div><b>{{ d.sales.pairs }}</b><small>pares</small></div>
          <div><b>Bs {{ d.sales.total | number: '1.0-0' }}</b><small>vendido</small></div>
        </div>

        <p class="section-title">Mis ventas de hoy</p>
        @for (s of d.sales.list; track s.code) {
          <div class="card row-card">
            <div class="grow"><b>{{ s.code }}</b><small class="muted sep">{{ s.time }} · {{ s.payment }}{{ s.customer ? ' · ' + s.customer : '' }}</small>
              @for (i of s.items; track $index) { <small class="line">{{ i }}</small> }
            </div>
            <b>Bs {{ s.total | number: '1.2-2' }}</b>
          </div>
        } @empty {
          <p class="muted">Todavía no vendiste hoy. ¡Vamos! 💪</p>
        }

        <p class="section-title">Lo que hice (últimos 7 días)</p>
        <div class="log">
          @for (a of d.activity; track $index) {
            <div class="log-line"><span class="log-icon">{{ a.icon }}</span><span class="grow">{{ a.text }}</span><small class="muted">{{ a.day }} {{ a.time }}</small></div>
          } @empty { <p class="muted">Sin actividad todavía.</p> }
        </div>
      } @else if (!error()) {
        <p class="muted">Cargando…</p>
      }

      <p class="section-title">🔠 Tamaño de la letra</p>
      <div class="pay sizes-opt">
        @for (o of text.options; track o.key) {
          <button [class.on]="text.current() === o.key" (click)="text.set(o.key)" [style.font-size.%]="o.pct">{{ o.label }}</button>
        }
      </div>
      <p class="muted small">También puedes tocar <b>Aa</b> arriba en cualquier pantalla.</p>

      <p class="section-title">Más</p>
      <div class="more">
        @if (session.can().publish) { <a class="secondary" [href]="session.me()?.links?.publish" target="_blank">📣 Publicar en la página (panel)</a> }
        @if (session.can().teach) { <a class="secondary" [href]="session.me()?.links?.teach" target="_blank">🎓 Enseñar al asistente</a> }
        <a class="secondary" [href]="session.me()?.links?.panel" target="_blank">🖥️ Abrir el panel completo</a>
        <button class="danger" (click)="logout()">Salir de la app</button>
      </div>
    </section>
  `,
})
export class TodayPage {
  private api = inject(Api);
  protected session = inject(Session);
  protected text = inject(TextSize);
  protected data = signal<any>(null);
  protected error = signal('');

  constructor() { this.load(); }

  async load() {
    try {
      this.data.set(await this.api.today());
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    }
  }

  logout() { if (confirm('¿Salir de la app?')) this.session.logout(); }
}
