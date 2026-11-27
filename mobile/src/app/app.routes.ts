import { inject } from '@angular/core';
import { CanActivateFn, Router, Routes } from '@angular/router';
import { Session } from './core/session';

/** Solo con sesión iniciada (si no, a «Entrar»). */
const loggedIn: CanActivateFn = async () => {
  const session = inject(Session);
  const router = inject(Router);   // antes del await: inject() solo funciona al inicio
  if (!session.loaded()) await session.load();
  return session.user() ? true : router.parseUrl('/entrar');
};

/** Pantallas que dependen de un permiso (el vendedor no ingresa stock, por ejemplo). */
const allowed = (perm: 'stock' | 'sell' | 'entry'): CanActivateFn => async () => {
  const session = inject(Session);
  const router = inject(Router);
  if (!session.loaded()) await session.load();
  if (!session.user()) return router.parseUrl('/entrar');
  if (session.can()[perm]) return true;
  return router.parseUrl(perm === 'stock' ? '/asistente' : '/tallas');   // sin bucles: «Tallas» lleva al asistente si no puede ver stock
};

export const routes: Routes = [
  { path: 'entrar', loadComponent: () => import('./pages/login').then((m) => m.LoginPage), title: 'Entrar · RadaWes' },
  {
    path: '',
    canActivate: [loggedIn],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'tallas' },
      { path: 'tallas', canActivate: [allowed('stock')], loadComponent: () => import('./pages/search').then((m) => m.SearchPage), title: 'Tallas · RadaWes' },
      { path: 'tallas/:id', canActivate: [allowed('stock')], loadComponent: () => import('./pages/search').then((m) => m.SearchPage), title: 'Tallas · RadaWes' },
      { path: 'vender', canActivate: [allowed('sell')], loadComponent: () => import('./pages/sell').then((m) => m.SellPage), title: 'Vender · RadaWes' },
      { path: 'ingresar', canActivate: [allowed('entry')], loadComponent: () => import('./pages/entry').then((m) => m.EntryPage), title: 'Ingresar · RadaWes' },
      { path: 'asistente', loadComponent: () => import('./pages/assistant').then((m) => m.AssistantPage), title: 'Asistente · RadaWes' },
      { path: 'hoy', loadComponent: () => import('./pages/today').then((m) => m.TodayPage), title: 'Mi día · RadaWes' },
    ],
  },
  { path: '**', redirectTo: 'tallas' },
];
