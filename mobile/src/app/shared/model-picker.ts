import { Component, ElementRef, inject, input, output, signal, viewChild } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { Api, errorText, isExpired } from '../core/api';
import { Session } from '../core/session';
import { ModelHit } from '../core/types';
import { Scanner } from './scanner';

/** Buscar un modelo por código o nombre (o escaneando la etiqueta). */
@Component({
  selector: 'app-model-picker',
  imports: [Scanner, DecimalPipe],
  template: `
    <div class="search-row">
      <input #box type="search" [value]="q()" (input)="type($any($event.target).value)" (keydown.enter)="first()"
             [placeholder]="placeholder()" autocomplete="off" autocapitalize="characters" enterkeyhint="search">
      @if (canScan) { <button class="icon-btn" (click)="scanning.set(true)" aria-label="Escanear etiqueta">📷</button> }
    </div>
    @if (error()) { <p class="error">{{ error() }}</p> }
    @if (busy()) { <p class="muted small">Buscando…</p> }
    @if (!busy() && q().length >= 2 && !results().length && !error()) {
      <p class="muted">No hay ningún modelo con «{{ q() }}». Revisa el código{{ session.can().entry ? ' o regístralo en 📦 Ingresar' : '' }}.</p>
    }
    <div class="list">
      @for (r of results(); track r.id) {
        <button class="hit" (click)="pick(r)">
          @if (r.image) { <img [src]="r.image" alt=""> } @else { <span class="noimg">👞</span> }
          <span class="hit-txt"><b>{{ r.name }}</b><small>{{ r.code || 'sin código' }} · Bs {{ r.price | number: '1.0-2' }}</small></span>
          <span class="badge" [class.zero]="!r.available">{{ r.available }} par{{ r.available === 1 ? '' : 'es' }}</span>
        </button>
      }
    </div>
    @if (scanning()) { <app-scanner (code)="scanned($event)" (closed)="scanning.set(false)" /> }
  `,
})
export class ModelPicker {
  private api = inject(Api);
  protected session = inject(Session);
  readonly placeholder = input('Código o nombre (ej.: CH-210, mocasín)');
  readonly picked = output<number>();
  protected q = signal('');
  protected results = signal<ModelHit[]>([]);
  protected busy = signal(false);
  protected error = signal('');
  protected scanning = signal(false);
  protected canScan = Scanner.supported();
  private box = viewChild<ElementRef<HTMLInputElement>>('box');
  private timer: any;
  private seq = 0;

  focus() { setTimeout(() => this.box()?.nativeElement.focus(), 50); }

  type(v: string) {
    this.q.set(v);
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.run(), 250);
  }

  private async run() {
    const q = this.q().trim();
    const mine = ++this.seq;
    this.error.set('');
    if (q.length < 2) { this.results.set([]); return; }
    this.busy.set(true);
    try {
      const r = await this.api.search(q, this.session.branchId());
      if (mine === this.seq) this.results.set(r);
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    } finally {
      if (mine === this.seq) this.busy.set(false);
    }
  }

  first() { const r = this.results()[0]; if (r) this.pick(r); }

  pick(r: ModelHit) {
    this.picked.emit(r.id);
    this.q.set('');
    this.results.set([]);
  }

  async scanned(code: string) {
    this.scanning.set(false);
    try {
      const r = await this.api.sku(code, this.session.branchId());
      if (r.ok) { this.picked.emit(r.product_id); return; }
    } catch { /* puede ser un código de modelo, se busca como texto */ }
    this.type(code);
  }
}
