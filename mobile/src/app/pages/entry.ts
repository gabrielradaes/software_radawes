import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api, errorText, isExpired } from '../core/api';
import { Session } from '../core/session';
import { ModelPicker } from '../shared/model-picker';

const fold = (s: string) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);
const sortSizes = (a: string[]) => [...a].sort((x, y) => (isNaN(+x) || isNaN(+y) ? x.localeCompare(y) : +x - +y));

/** Llegaron pares: modelo (o uno nuevo) → colores y tallas → cuántos de cada uno. */
@Component({
  selector: 'page-entry',
  imports: [ModelPicker, FormsModule, RouterLink],
  template: `
    <section class="page">
      @if (done(); as d) {
        <div class="success">
          <div class="big-emoji">📦</div>
          <h1>¡Listo!</h1>
          <p>{{ d.msg }}</p>
          <a class="primary big" [routerLink]="['/tallas', d.id]">🔎 Ver sus tallas</a>
          <button class="secondary big" (click)="reset()">📦 Ingresar otro</button>
        </div>
      } @else {
        <h1 class="page-title">📦 Llegaron pares</h1>

        <p class="step"><span>1</span> ¿Qué modelo?</p>
        <div class="two">
          <button [class.on]="mode() === 'buscar'" (click)="setMode('buscar')">🔎 Ya existe</button>
          @if (session.can().new_model) { <button [class.on]="mode() === 'nuevo'" (click)="setMode('nuevo')">➕ Modelo nuevo</button> }
        </div>

        @if (mode() === 'buscar') {
          @if (!product()) {
            <app-model-picker (picked)="pick($event)" />
          } @else {
            <div class="chosen"><b>{{ product()!.name }}</b><small>{{ product()!.code || 'sin código' }}</small><button class="ghost" (click)="product.set(null)">Cambiar</button></div>
          }
        }
        @if (mode() === 'nuevo') {
          <div class="form">
            <label>Código <input [(ngModel)]="f.code" placeholder="Ej.: 596101-001" autocapitalize="characters"></label>
            <label>Nombre <small class="muted">(si no tiene, queda Marca + código)</small><input [(ngModel)]="f.name" placeholder="Ej.: Botín Chelsea"></label>
            <label>Marca <input [(ngModel)]="f.brand" list="brands" placeholder="Ej.: Demócrata"></label>
            <datalist id="brands">@for (b of session.me()?.brands; track b) { <option [value]="b"></option> }</datalist>
            <label>Clase
              <select [(ngModel)]="f.category_id"><option [ngValue]="null">Elegir…</option>@for (c of session.me()?.classes; track c.id) { <option [ngValue]="c.id">{{ c.name }}</option> }</select>
            </label>
            <label>Precio de venta (Bs) <input type="number" inputmode="decimal" min="0" [(ngModel)]="f.price"></label>
          </div>
        }

        @if (ready()) {
          <p class="step"><span>2</span> Colores y tallas</p>
          <div class="chips">
            @for (c of colors(); track c) { <span class="chip on">{{ c }} <button (click)="removeColor(c)" aria-label="Quitar">×</button></span> }
          </div>
          <div class="search-row">
            <input [(ngModel)]="newColor" list="colors" placeholder="Agregar color (ej.: Negro)" (keydown.enter)="addColor()">
            <button class="icon-btn" (click)="addColor()">＋</button>
          </div>
          <datalist id="colors">@for (c of session.me()?.colors; track c) { <option [value]="c"></option> }</datalist>
          <div class="chips sizes-pick">
            @for (t of sizeChoices(); track t) { <button class="chip" [class.on]="tallas().includes(t)" (click)="toggle(t)">{{ t }}</button> }
          </div>
          <div class="search-row">
            <input [(ngModel)]="newSize" placeholder="Otra talla (ej.: 46, 41.5)" (keydown.enter)="addSize()">
            <button class="icon-btn" (click)="addSize()">＋</button>
          </div>

          <p class="step"><span>3</span> ¿Cuántos pares llegaron? <small class="muted">(se suman a lo que hay)</small></p>
          <div class="grid-wrap">
            <table class="sizes entry">
              <thead><tr><th></th>@for (t of cols(); track t) { <th>{{ t || 'Cant.' }}</th> }</tr></thead>
              <tbody>
                @for (c of rows(); track c) {
                  <tr>
                    <th class="color">{{ c || 'Único' }}</th>
                    @for (t of cols(); track t) {
                      <td>
                        <input type="number" inputmode="numeric" min="0" max="999" [ngModel]="qty()[key(c, t)]" (ngModelChange)="setQty(c, t, $event)" [class.filled]="+(qty()[key(c, t)] || 0) > 0">
                        @if (have()[key(c, t)] !== undefined) { <small class="have">hay {{ have()[key(c, t)] }}</small> }
                      </td>
                    }
                  </tr>
                }
              </tbody>
            </table>
          </div>
          <div class="search-row"><button class="secondary" (click)="fillAll(1)">Poner 1 en todas</button><button class="ghost" (click)="qty.set({})">Limpiar</button></div>
          <label>Nota (opcional) <input [(ngModel)]="note" placeholder="Ej.: llegó de São Paulo"></label>

          @if (error()) { <p class="error">{{ error() }}</p> }
          <button class="primary big sticky" [disabled]="busy() || !canSave()" (click)="save()">{{ busy() ? 'Guardando…' : 'Guardar ingreso (' + total() + ' par' + (total() === 1 ? '' : 'es') + ')' }}</button>
        }
      }
    </section>
  `,
})
export class EntryPage {
  private api = inject(Api);
  protected session = inject(Session);
  readonly producto = input<string>();
  protected mode = signal<'buscar' | 'nuevo'>('buscar');
  protected product = signal<{ id: number; name: string; code: string | null } | null>(null);
  protected colors = signal<string[]>([]);
  protected tallas = signal<string[]>([]);
  protected qty = signal<Record<string, number | null>>({});
  protected have = signal<Record<string, number>>({});
  protected extra = signal<string[]>([]);
  protected f = { code: '', name: '', brand: '', category_id: null as number | null, price: null as number | null };
  protected newColor = '';
  protected newSize = '';
  protected note = '';
  protected busy = signal(false);
  protected error = signal('');
  protected done = signal<{ id: number; msg: string } | null>(null);

  protected ready = computed(() => this.mode() === 'nuevo' || !!this.product());
  protected rows = computed(() => (this.colors().length ? this.colors() : ['']));
  protected cols = computed(() => (this.tallas().length ? this.tallas() : ['']));
  protected total = computed(() => Object.values(this.qty()).reduce<number>((s, v) => s + (Number(v) || 0), 0));
  protected sizeChoices = computed(() => sortSizes([...new Set(['36', '37', '38', '39', '40', '41', '42', '43', '44', ...this.extra(), ...this.tallas()])]));

  constructor() {
    effect(() => {
      const id = Number(this.producto());
      untracked(() => { if (id) this.pick(id); });
    });
  }

  key(c: string, t: string) { return fold(c) + '|' + (t || ''); }

  setMode(m: 'buscar' | 'nuevo') {
    this.mode.set(m);
    this.product.set(null);
    this.have.set({});
    if (m === 'nuevo') { this.colors.set([]); this.tallas.set([]); this.qty.set({}); }
  }

  async pick(id: number) {
    this.error.set('');
    try {
      const g = await this.api.model(id, this.session.branchId());
      this.mode.set('buscar');
      this.product.set({ id: g.product.id, name: g.product.name, code: g.product.code });
      this.colors.set(g.colors.map((c) => c.name).filter((n) => n !== 'Único'));
      this.tallas.set(g.sizes.filter((s) => s !== 'Única'));
      const have: Record<string, number> = {};
      for (const [c, sizes] of Object.entries(g.cells[g.branch_id] ?? {})) {
        for (const [s, cell] of Object.entries(sizes)) have[this.key(c === 'Único' ? '' : c, s === 'Única' ? '' : s)] = cell.available;
      }
      this.have.set(have);
      this.qty.set({});
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    }
  }

  addColor() {
    const c = cap(this.newColor.trim().replace(/\s+/g, ' '));
    if (c && !this.colors().some((x) => fold(x) === fold(c))) this.colors.update((l) => [...l, c]);
    this.newColor = '';
  }
  removeColor(c: string) { this.colors.update((l) => l.filter((x) => x !== c)); }
  toggle(t: string) { this.tallas.update((l) => (l.includes(t) ? l.filter((x) => x !== t) : sortSizes([...l, t]))); }
  addSize() {
    const t = this.newSize.trim().replace(',', '.');
    if (/^[0-9A-Za-z./ ]{1,10}$/.test(t)) {
      if (!this.extra().includes(t)) this.extra.update((l) => [...l, t]);
      if (!this.tallas().includes(t)) this.toggle(t);
    }
    this.newSize = '';
  }
  setQty(c: string, t: string, v: number | null) { this.qty.update((q) => ({ ...q, [this.key(c, t)]: v === null || (v as any) === '' ? null : Math.max(0, Math.floor(+v)) })); }
  fillAll(n: number) {
    const q: Record<string, number> = {};
    for (const c of this.rows()) for (const t of this.cols()) q[this.key(c, t)] = n;
    this.qty.set(q);
  }

  canSave() {
    if (this.mode() === 'nuevo') return !!(this.f.code.trim() || this.f.name.trim()) && !!this.f.category_id && this.f.price !== null;
    return !!this.product() && this.total() > 0;
  }

  async save() {
    const fd = new FormData();
    const branch = this.session.branchId();
    if (branch && !this.session.user()?.scoped) fd.append('branch_id', String(branch));
    if (this.mode() === 'buscar') {
      fd.append('product_id', String(this.product()!.id));
    } else {
      fd.append('code', this.f.code.trim());
      fd.append('name', this.f.name.trim());
      fd.append('brand', this.f.brand.trim());
      fd.append('category_id', String(this.f.category_id ?? ''));
      fd.append('price', String(this.f.price ?? ''));
      fd.append('show_web', '1');
    }
    this.colors().forEach((c, i) => fd.append(`colors[${i}]`, c));
    this.tallas().forEach((t) => fd.append('tallas[]', t));
    this.rows().forEach((c, ci) => this.cols().forEach((t) => {
      const v = this.qty()[this.key(c, t)];
      if (v !== null && v !== undefined && +v > 0) fd.append(`q[${ci}][${t || '_'}]`, String(v));
    }));
    if (this.note.trim()) fd.append('note', this.note.trim());

    const what = this.mode() === 'buscar' ? this.product()!.name : (this.f.name.trim() || [this.f.brand.trim(), this.f.code.trim()].filter(Boolean).join(' '));
    if (!confirm(this.total() ? `¿Ingresar ${this.total()} par(es) de ${what}?` : `¿Crear ${what} sin pares por ahora?`)) return;
    this.busy.set(true);
    this.error.set('');
    try {
      const r = await this.api.entry(fd);
      const id = Number(new URL(r.redirect, location.origin).searchParams.get('hecho'));
      navigator.vibrate?.([40, 60, 40]);
      this.done.set({ id, msg: this.total() ? `Entraron ${this.total()} par(es) de ${what}.` : `${what} quedó registrado.` });
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    } finally {
      this.busy.set(false);
    }
  }

  reset() {
    this.done.set(null);
    this.setMode('buscar');
    this.colors.set([]);
    this.tallas.set([]);
    this.f = { code: '', name: '', brand: '', category_id: null, price: null };
    this.note = '';
  }
}
