import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Api } from './api';
import { Me } from './types';

/** Quién usa la app y en qué tienda trabaja (el admin elige; supervisor y vendedor tienen la suya). */
@Injectable({ providedIn: 'root' })
export class Session {
  private api = inject(Api);
  private router = inject(Router);

  readonly me = signal<Me | null>(null);
  readonly loaded = signal(false);
  readonly user = computed(() => this.me()?.user ?? null);
  readonly can = computed(() => this.me()?.can ?? { stock: false, sell: false, entry: false, new_model: false, publish: false, teach: false });
  private chosen = signal<number | null>(this.read());

  /** Tienda con la que se trabaja ahora. */
  readonly branchId = computed<number | null>(() => {
    const me = this.me();
    if (!me?.user) return null;
    if (me.user.scoped) return me.user.branch_id;
    const list = me.branches ?? [];
    return list.some((b) => b.id === this.chosen()) ? this.chosen() : (me.user.branch_id ?? list[0]?.id ?? null);
  });
  readonly branchName = computed(() => this.me()?.branches?.find((b) => b.id === this.branchId())?.name ?? '');

  private loading: Promise<void> | null = null;

  /** Pregunta al servidor quién soy (una sola vez aunque lo pidan varias pantallas a la vez). */
  load(): Promise<void> {
    return (this.loading ??= this.api.me()
      .then((me) => this.me.set(me))
      .catch(() => this.me.set(null))
      .finally(() => { this.loaded.set(true); this.loading = null; }));
  }

  async login(email: string, password: string) {
    this.me.set(await this.api.login(email, password));
  }

  async logout() {
    try { await this.api.logout(); } catch { /* igual se sale */ }
    this.me.set({ user: null });
    await this.api.me().catch(() => null);   // nueva cookie XSRF para el próximo ingreso
    this.router.navigateByUrl('/entrar');
  }

  /** La sesión venció en el servidor: volver a entrar. */
  expired() {
    this.me.set({ user: null });
    this.router.navigateByUrl('/entrar');
  }

  chooseBranch(id: number) {
    this.chosen.set(id);
    try { localStorage.setItem('rye-app-branch', String(id)); } catch { /* sin almacenamiento */ }
  }

  private read(): number | null {
    try { const v = localStorage.getItem('rye-app-branch'); return v ? Number(v) : null; } catch { return null; }
  }
}
