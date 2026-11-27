import { Component, computed, input, output } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { Cell, ModelGrid } from '../core/types';

export interface Pick { color: string; size: string; cell: Cell }

/** Colores × tallas de un modelo en mi tienda, con un resumen de las otras tiendas. */
@Component({
  selector: 'app-model-grid',
  imports: [DecimalPipe],
  template: `
    @let g = grid();
    <div class="model-head">
      @if (g.product.image) { <img [src]="g.product.image" alt=""> } @else { <span class="noimg big">👞</span> }
      <div>
        <h2>{{ g.product.name }}</h2>
        <p class="muted">{{ g.product.code || 'sin código' }}{{ g.product.category ? ' · ' + g.product.category : '' }}</p>
        <p class="price">Bs {{ g.product.price | number: '1.0-2' }}</p>
      </div>
    </div>

    <p class="section-title">En {{ mine()?.name }} <span class="muted">· {{ total() }} par{{ total() === 1 ? '' : 'es' }}</span></p>
    @if (!g.sizes.length) {
      <p class="muted">Este modelo todavía no tiene tallas ni colores.</p>
    } @else {
      <div class="grid-wrap">
        <table class="sizes">
          <thead><tr><th></th>@for (s of g.sizes; track s) { <th>{{ s }}</th> }</tr></thead>
          <tbody>
            @for (c of g.colors; track c.name) {
              <tr>
                <th class="color"><span class="dot" [style.background]="c.hex || '#bbb'"></span>{{ c.name }}</th>
                @for (s of g.sizes; track s) {
                  @let cell = g.cells[g.branch_id]?.[c.name]?.[s];
                  <td>
                    @if (cell) {
                      <button class="size" [class.zero]="cell.available < 1" [class.sel]="isSelected(cell)" [disabled]="pickMode() && cell.available < 1"
                              (click)="tap.emit({ color: c.name, size: s, cell })">
                        {{ cell.available }}@if (cell.reserved) {<small>+{{ cell.reserved }}📌</small>}
                      </button>
                    } @else { <span class="none">·</span> }
                  </td>
                }
              </tr>
            }
          </tbody>
        </table>
      </div>
      <p class="muted small">{{ pickMode() ? 'Toca la talla que se lleva el cliente.' : 'El número es cuántos pares hay para vender. 📌 = apartados.' }}</p>
    }

    @if (others().length) {
      <p class="section-title">En otras tiendas</p>
      @for (o of others(); track o.name) {
        <div class="other"><b>{{ o.name }}</b> <span>{{ o.text || 'nada' }}</span></div>
      }
    }
  `,
})
export class ModelGridView {
  readonly grid = input.required<ModelGrid>();
  readonly pickMode = input(false);
  readonly selected = input<number[]>([]);
  readonly tap = output<Pick>();

  protected mine = computed(() => this.grid().branches.find((b) => b.id === this.grid().branch_id));
  protected total = computed(() => {
    const g = this.grid();
    let n = 0;
    for (const c of Object.values(g.cells[g.branch_id] ?? {})) for (const cell of Object.values(c)) n += cell.available;
    return n;
  });
  protected others = computed(() => {
    const g = this.grid();
    return g.branches.filter((b) => b.id !== g.branch_id).map((b) => {
      const parts: string[] = [];
      for (const [color, sizes] of Object.entries(g.cells[b.id] ?? {})) {
        const have = Object.entries(sizes).filter(([, c]) => c.available > 0).map(([s, c]) => s + (c.available > 1 ? ` (${c.available})` : ''));
        if (have.length) parts.push((g.colors.length > 1 ? color + ': ' : '') + have.join(', '));
      }
      return { name: b.name, text: parts.join(' · ') };
    });
  });

  protected isSelected(cell: Cell) { return this.selected().includes(cell.variant_id); }
}
