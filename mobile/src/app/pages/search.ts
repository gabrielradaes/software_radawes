import { Component, effect, inject, input, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Api, errorText, isExpired } from '../core/api';
import { Session } from '../core/session';
import { ModelGrid } from '../core/types';
import { ModelPicker } from '../shared/model-picker';
import { ModelGridView } from '../shared/model-grid';

/** ¿Hay talla? Buscar el modelo y ver colores × tallas en mi tienda y en las otras. */
@Component({
  selector: 'page-search',
  imports: [ModelPicker, ModelGridView, RouterLink],
  template: `
    <section class="page">
      @if (!id()) {
        <h1 class="page-title">🔎 ¿Hay talla?</h1>
        <p class="muted">Escribe el código o el nombre, o escanea la etiqueta.</p>
        <app-model-picker (picked)="open($event)" />
      } @else {
        <button class="back" (click)="router.navigateByUrl('/tallas')">← Buscar otro</button>
        @if (error()) { <p class="error">{{ error() }}</p> }
        @if (loading() && !grid()) { <p class="muted">Cargando…</p> }
        @if (grid(); as g) {
          <app-model-grid [grid]="g" />
          <div class="actions">
            @if (session.can().sell) { <a class="primary big" [routerLink]="['/vender']" [queryParams]="{ producto: g.product.id }">🧾 Vender este</a> }
            @if (session.can().entry) { <a class="secondary big" [routerLink]="['/ingresar']" [queryParams]="{ producto: g.product.id }">📦 Llegaron pares</a> }
          </div>
        }
      }
    </section>
  `,
})
export class SearchPage {
  private api = inject(Api);
  protected session = inject(Session);
  protected router = inject(Router);
  readonly id = input<string>();
  protected grid = signal<ModelGrid | null>(null);
  protected loading = signal(false);
  protected error = signal('');

  constructor() {
    // Se recarga si cambia el modelo o la tienda elegida
    effect(() => {
      const id = Number(this.id());
      const branch = this.session.branchId();
      untracked(() => { if (id) this.load(id, branch); else this.grid.set(null); });
    });
  }

  open(id: number) { this.router.navigate(['/tallas', id]); }

  private async load(id: number, branch: number | null) {
    this.loading.set(true);
    this.error.set('');
    try {
      this.grid.set(await this.api.model(id, branch));
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    } finally {
      this.loading.set(false);
    }
  }
}
