import { Component, ElementRef, OnDestroy, OnInit, output, signal, viewChild } from '@angular/core';

declare const BarcodeDetector: any;

/** Lector de etiquetas con la cámara del celular (Android/Chrome). Si el celular no puede, se escribe el código. */
@Component({
  selector: 'app-scanner',
  template: `
    <div class="scanner">
      <video #video playsinline muted></video>
      <div class="scan-frame"></div>
      <p class="scan-msg">{{ msg() }}</p>
      <button class="secondary" (click)="closed.emit()">Cerrar</button>
    </div>
  `,
})
export class Scanner implements OnInit, OnDestroy {
  readonly code = output<string>();
  readonly closed = output<void>();
  protected msg = signal('Apunta a la etiqueta 🏷️');
  private video = viewChild.required<ElementRef<HTMLVideoElement>>('video');
  private stream: MediaStream | null = null;
  private timer: any;

  static supported(): boolean {
    return typeof window !== 'undefined' && 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia;
  }

  async ngOnInit() {
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      const v = this.video().nativeElement;
      v.srcObject = this.stream;
      await v.play();
      const detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'ean_8', 'qr_code', 'code_39'] });
      this.timer = setInterval(async () => {
        try {
          const found = await detector.detect(v);
          if (found?.length) {
            navigator.vibrate?.(80);
            this.code.emit(String(found[0].rawValue).trim());
          }
        } catch { /* sigue intentando */ }
      }, 300);
    } catch {
      this.msg.set('No se pudo abrir la cámara. Permite la cámara o escribe el código.');
    }
  }

  ngOnDestroy() {
    clearInterval(this.timer);
    this.stream?.getTracks().forEach((t) => t.stop());
  }
}
