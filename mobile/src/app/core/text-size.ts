import { Injectable, signal } from '@angular/core';

/** Tamaño de letra de la app (cada celular recuerda el suyo). Toda la app crece junto: letras, botones y tallas. */
@Injectable({ providedIn: 'root' })
export class TextSize {
  readonly options = [
    { key: 'normal', label: 'Normal', pct: 100 },
    { key: 'grande', label: 'Grande', pct: 112.5 },
    { key: 'muy-grande', label: 'Muy grande', pct: 125 },
    { key: 'enorme', label: 'Enorme', pct: 140 },
  ];
  readonly current = signal(this.read());
  /** Cuánto hay que agrandar porque el celular abrió la app en «modo escritorio» (1 = no hace falta). */
  readonly fit = signal(1);

  constructor() {
    this.measure();
    this.apply();
    addEventListener('resize', () => { if (this.measure()) this.apply(); });
  }

  set(key: string) {
    if (!this.options.some((o) => o.key === key)) return;
    this.current.set(key);
    try { localStorage.setItem('rye-app-letra', key); } catch { /* sin almacenamiento */ }
    this.apply();
  }

  /** «Aa»: pasa al siguiente tamaño (y del más grande vuelve al normal). */
  next() {
    const i = this.options.findIndex((o) => o.key === this.current());
    this.set(this.options[(i + 1) % this.options.length].key);
  }

  get label() { return this.options.find((o) => o.key === this.current())?.label ?? ''; }

  private apply() {
    const o = this.options.find((x) => x.key === this.current()) ?? this.options[1];
    document.documentElement.style.fontSize = (o.pct * this.fit()) + '%';
  }

  /**
   * Con «Sitio de escritorio» activado, Chrome dibuja la página como si la pantalla midiera ~980 px
   * y luego la achica: todo se ve diminuto. Si la pantalla es de celular pero la página quedó mucho
   * más ancha, agrandamos todo en la misma proporción y se ve igual que en un celular normal.
   */
  private measure(): boolean {
    const page = document.documentElement.clientWidth;
    const phone = screen.width;
    const f = phone > 0 && phone < 700 && page > phone * 1.25 ? page / phone : 1;
    if (Math.abs(f - this.fit()) < 0.01) return false;
    this.fit.set(f);
    return true;
  }

  private read(): string {
    try { return localStorage.getItem('rye-app-letra') || 'grande'; } catch { return 'grande'; }
  }
}
