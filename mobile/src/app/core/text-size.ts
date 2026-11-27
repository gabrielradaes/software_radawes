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

  constructor() { this.apply(); }

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
    document.documentElement.style.fontSize = o.pct + '%';
  }

  private read(): string {
    try { return localStorage.getItem('rye-app-letra') || 'grande'; } catch { return 'grande'; }
  }
}
