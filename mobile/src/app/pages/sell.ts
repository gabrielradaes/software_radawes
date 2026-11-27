import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Api, errorText, isExpired } from '../core/api';
import { Session } from '../core/session';
import { ModelGrid } from '../core/types';
import { ModelPicker } from '../shared/model-picker';
import { ModelGridView, Pick } from '../shared/model-grid';

interface Line { variant_id: number; name: string; detail: string; price: number; qty: number; max: number }

/** Vender en 3 toques: modelo → talla → cobrar. */
@Component({
  selector: 'page-sell',
  imports: [ModelPicker, ModelGridView, FormsModule, DecimalPipe],
  template: `
    <section class="page">
      @if (done(); as d) {
        <div class="success">
          <div class="big-emoji">✅</div>
          <h1>Venta {{ d.code }}</h1>
          <p class="price">Bs {{ d.total | number: '1.2-2' }}</p>
          @if (d.customer) { <p>Cliente: <b>{{ d.customer }}</b></p> }
          <p class="muted">El stock ya se descontó.</p>
          <button class="primary big" (click)="reset()">🧾 Nueva venta</button>
          <a class="secondary" [href]="d.url" target="_blank">Ver en el panel</a>
        </div>
      } @else {
        <h1 class="page-title">🧾 Vender</h1>

        <p class="step"><span>1</span> ¿Qué se lleva?</p>
        @if (!grid()) {
          <app-model-picker (picked)="openModel($event)" />
        } @else {
          <button class="back" (click)="grid.set(null)">← Otro modelo</button>
          <app-model-grid [grid]="grid()!" [pickMode]="true" [selected]="selectedIds()" (tap)="add($event)" />
        }
        @if (note()) { <p class="toast">{{ note() }}</p> }

        @if (lines().length) {
          <div class="card cart">
            @for (l of lines(); track l.variant_id) {
              <div class="cart-line">
                <div class="grow"><b>{{ l.name }}</b><small>{{ l.detail }}</small></div>
                <div class="qty">
                  <button (click)="change(l, -1)" aria-label="Uno menos">−</button><span>{{ l.qty }}</span><button (click)="change(l, 1)" [disabled]="l.qty >= l.max" aria-label="Uno más">+</button>
                </div>
                <input class="price-in" type="number" inputmode="decimal" min="0" [ngModel]="l.price" (ngModelChange)="setPrice(l, $event)" aria-label="Precio">
              </div>
            }
            <div class="cart-total"><span>Total</span><b>Bs {{ total() | number: '1.2-2' }}</b></div>
          </div>

          <p class="step"><span>2</span> Cliente <small class="muted">(opcional)</small></p>
          <input type="tel" inputmode="tel" placeholder="Celular, ej.: 70011222" [(ngModel)]="phone" (ngModelChange)="lookup()">
          @if (customerName()) { <p class="ok">👤 {{ customerName() }}</p> }
          @if (newCustomer()) { <input type="text" placeholder="Nombre del cliente (nuevo)" [(ngModel)]="name" autocapitalize="words"> }

          <p class="step"><span>3</span> ¿Cómo pagó?</p>
          <div class="pay">
            @for (p of session.me()?.payments; track p.key) {
              <button [class.on]="payment() === p.key" (click)="payment.set(p.key)">{{ icon(p.key) }} {{ p.label }}</button>
            }
          </div>

          @if (error()) { <p class="error">{{ error() }}</p> }
          <button class="primary big sticky" [disabled]="busy()" (click)="charge()">{{ busy() ? 'Guardando…' : 'Cobrar Bs ' + (total() | number: '1.2-2') }}</button>
        }
      }
    </section>
  `,
})
export class SellPage {
  private api = inject(Api);
  protected session = inject(Session);
  readonly producto = input<string>();
  protected grid = signal<ModelGrid | null>(null);
  protected lines = signal<Line[]>([]);
  protected payment = signal('efectivo');
  protected phone = '';
  protected name = '';
  protected customerName = signal('');
  protected newCustomer = signal(false);
  protected busy = signal(false);
  protected error = signal('');
  protected note = signal('');
  protected done = signal<{ code: string; total: number; customer: string | null; url: string } | null>(null);
  protected total = computed(() => this.lines().reduce((s, l) => s + l.price * l.qty, 0));
  protected selectedIds = computed(() => this.lines().map((l) => l.variant_id));
  private lookupTimer: any;
  private branchSeen: number | null = null;

  constructor() {
    effect(() => {
      const id = Number(this.producto());
      const branch = this.session.branchId();
      untracked(() => {
        if (this.branchSeen !== null && branch !== this.branchSeen && this.lines().length) {
          this.lines.set([]);
          this.flash('Cambiaste de tienda: la venta empezó de nuevo.');
        }
        this.branchSeen = branch;
        if (id && !this.grid()) this.openModel(id);
      });
    });
  }

  async openModel(id: number) {
    this.error.set('');
    try {
      this.grid.set(await this.api.model(id, this.session.branchId()));
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    }
  }

  add(p: Pick) {
    const g = this.grid()!;
    const existing = this.lines().find((l) => l.variant_id === p.cell.variant_id);
    if (existing) {
      if (existing.qty >= p.cell.available) { this.flash('No hay más pares de esa talla.'); return; }
      this.change(existing, 1);
    } else {
      const detail = [p.color !== 'Único' ? p.color : '', p.size !== 'Única' ? 'talla ' + p.size : ''].filter(Boolean).join(' · ');
      this.lines.update((ls) => [...ls, { variant_id: p.cell.variant_id, name: g.product.name, detail, price: p.cell.price, qty: 1, max: p.cell.available }]);
    }
    navigator.vibrate?.(30);
    this.flash('✔ Agregado: ' + g.product.name + ' ' + p.size);
  }

  change(l: Line, d: number) {
    this.lines.update((ls) => ls.map((x) => (x.variant_id === l.variant_id ? { ...x, qty: x.qty + d } : x)).filter((x) => x.qty > 0));
  }

  setPrice(l: Line, v: number) {
    this.lines.update((ls) => ls.map((x) => (x.variant_id === l.variant_id ? { ...x, price: Math.max(0, Number(v) || 0) } : x)));
  }

  lookup() {
    clearTimeout(this.lookupTimer);
    this.customerName.set('');
    this.newCustomer.set(false);
    const digits = this.phone.replace(/\D+/g, '');
    if (digits.length < 7) return;
    this.lookupTimer = setTimeout(async () => {
      try {
        const r = await this.api.customer(digits);
        if (r.found) this.customerName.set(r.name + (r.size ? ' · calza ' + r.size : ''));
        else this.newCustomer.set(true);
      } catch { /* se puede vender igual */ }
    }, 400);
  }

  async charge() {
    const lines = this.lines();
    if (!lines.length) return;
    if (!confirm('¿Registrar la venta por Bs ' + this.total().toFixed(2) + '?')) return;
    this.busy.set(true);
    this.error.set('');
    try {
      const r = await this.api.sell({
        branch_id: this.session.branchId(),
        items: lines.map((l) => ({ variant_id: l.variant_id, quantity: l.qty, price: l.price })),
        payment_method: this.payment(),
        customer_phone: this.phone.trim() || null,
        customer_name: this.name.trim() || null,
      });
      navigator.vibrate?.([40, 60, 40]);
      this.done.set(r);
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    } finally {
      this.busy.set(false);
    }
  }

  reset() {
    this.done.set(null);
    this.lines.set([]);
    this.grid.set(null);
    this.phone = '';
    this.name = '';
    this.customerName.set('');
    this.newCustomer.set(false);
    this.payment.set('efectivo');
  }

  icon(key: string) { return ({ efectivo: '💵', qr: '📱', tarjeta: '💳', transferencia: '🏦' } as Record<string, string>)[key] ?? '💰'; }

  private flash(t: string) {
    this.note.set(t);
    setTimeout(() => { if (this.note() === t) this.note.set(''); }, 1800);
  }
}
