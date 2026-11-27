import { HttpClient, HttpErrorResponse, HttpInterceptorFn, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ChatMessage, Me, ModelGrid, ModelHit } from './types';

/** Todas las llamadas piden JSON (así Laravel responde 401/422 en vez de redirigir). */
export const apiInterceptor: HttpInterceptorFn = (req, next) =>
  next(req.clone({ setHeaders: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, withCredentials: true }));

/** Mensaje claro para cualquier error del servidor o de la conexión. */
export function errorText(e: unknown): string {
  if (e instanceof HttpErrorResponse) {
    const msg: string | undefined = e.error?.message;
    if (e.status === 0) return 'Sin conexión. Revisa tu internet y vuelve a intentar.';
    if (e.status === 401) return 'Tu sesión terminó. Vuelve a entrar.';
    if (e.status === 403) return msg && msg !== 'This action is unauthorized.' ? msg : 'Tu usuario no tiene permiso para esto.';
    if (e.status === 419) return 'La página estuvo abierta mucho tiempo. Toca otra vez para reintentar.';
    if (e.status === 422 && e.error?.errors) return (Object.values(e.error.errors) as string[][]).flat().join(' ');
    if (e.status === 429) return 'Demasiados intentos seguidos. Espera un minuto.';
    return msg && e.status < 500 ? msg : 'Algo falló en el servidor (error ' + e.status + '). Intenta de nuevo.';
  }
  return 'Algo salió mal. Intenta de nuevo.';
}

export function isExpired(e: unknown): boolean {
  return e instanceof HttpErrorResponse && e.status === 401;
}

@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);

  me() { return firstValueFrom(this.http.get<Me>('/api/app/me')); }
  login(email: string, password: string) { return firstValueFrom(this.http.post<Me>('/api/app/login', { email, password })); }
  logout() { return firstValueFrom(this.http.post('/api/app/logout', {})); }

  search(q: string, branch: number | null) {
    return firstValueFrom(this.http.get<ModelHit[]>('/admin/stock/buscar', { params: this.params({ q, sucursal: branch }) }));
  }
  model(id: number, branch: number | null) {
    return firstValueFrom(this.http.get<ModelGrid>('/admin/stock/modelo/' + id, { params: this.params({ sucursal: branch }) }));
  }
  sku(code: string, branch: number | null) {
    return firstValueFrom(this.http.get<{ ok: boolean; product_id: number; label: string; message?: string }>('/admin/stock/sku', { params: this.params({ code, sucursal: branch }) }));
  }
  customer(phone: string) {
    return firstValueFrom(this.http.get<{ found: boolean; name?: string; stage?: string; size?: string }>('/admin/clientes/buscar', { params: { phone } }));
  }
  sell(body: object) {
    return firstValueFrom(this.http.post<{ ok: boolean; code: string; total: number; customer: string | null; url: string }>('/api/app/ventas', body));
  }
  /** Mismo «Ingresar calzado» del panel: responde {redirect} con ?hecho=<id del modelo>. */
  entry(form: FormData) {
    return firstValueFrom(this.http.post<{ redirect: string }>('/admin/stock/ingresar', form, { headers: { 'X-RyE-Upload': '1' } }));
  }
  today() { return firstValueFrom(this.http.get<any>('/api/app/hoy')); }

  chatHistory() { return firstValueFrom(this.http.get<{ pending: boolean; messages: ChatMessage[] }>('/api/app/asistente')); }
  chatSend(text: string, images: File[] = []) {
    const fd = new FormData();
    fd.append('text', text);
    images.forEach((f) => fd.append('images[]', f));
    return firstValueFrom(this.http.post<ChatMessage>('/admin/asistente/mensaje', fd));
  }
  chatConfirm() { return firstValueFrom(this.http.post<ChatMessage>('/admin/asistente/confirmar', {})); }
  chatCancel() { return firstValueFrom(this.http.post<ChatMessage>('/admin/asistente/cancelar', {})); }
  chatLearn(intent: string) { return firstValueFrom(this.http.post<ChatMessage>('/admin/asistente/aprender', { intent })); }

  private params(p: Record<string, string | number | null>) {
    let hp = new HttpParams();
    for (const [k, v] of Object.entries(p)) if (v !== null && v !== '') hp = hp.set(k, String(v));
    return hp;
  }
}
