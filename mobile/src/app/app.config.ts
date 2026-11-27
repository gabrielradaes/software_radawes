import { ApplicationConfig, provideBrowserGlobalErrorListeners, provideZoneChangeDetection, isDevMode, LOCALE_ID } from '@angular/core';
import { provideRouter, withComponentInputBinding, withHashLocation } from '@angular/router';
import { provideHttpClient, withInterceptors, withXsrfConfiguration } from '@angular/common/http';
import { provideServiceWorker } from '@angular/service-worker';
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es-BO';

import { routes } from './app.routes';
import { apiInterceptor } from './core/api';

registerLocaleData(localeEs);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZoneChangeDetection({ eventCoalescing: true }),
    // Rutas con # (/app/#/vender): funcionan al recargar sin configurar nada en el hosting
    provideRouter(routes, withHashLocation(), withComponentInputBinding()),
    // Misma sesión que el panel: Laravel deja la cookie XSRF-TOKEN y Angular la devuelve en X-XSRF-TOKEN
    provideHttpClient(withXsrfConfiguration({ cookieName: 'XSRF-TOKEN', headerName: 'X-XSRF-TOKEN' }), withInterceptors([apiInterceptor])),
    { provide: LOCALE_ID, useValue: 'es-BO' },
    provideServiceWorker('ngsw-worker.js', { enabled: !isDevMode(), registrationStrategy: 'registerWhenStable:30000' }),
  ],
};
