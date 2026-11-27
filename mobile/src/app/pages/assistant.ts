import { Component, ElementRef, OnDestroy, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorText, isExpired } from '../core/api';
import { Session } from '../core/session';
import { ChatMessage } from '../core/types';

declare const webkitSpeechRecognition: any;

/** El mismo asistente del panel, con micrófono. Si no entiende, se le enseña con un toque. */
@Component({
  selector: 'page-assistant',
  imports: [FormsModule],
  template: `
    <section class="chat">
      <div class="chat-list" #list>
        @if (!messages().length) {
          <div class="bubble bot"><p>Hola {{ session.user()?.first_name }} 👋 Dime qué necesitas, por ejemplo: «¿hay el CH-210 en 41?», «vendí un CH-210 41 negro en efectivo» o «llegaron 5 pares del CH-210 talla 40».</p></div>
        }
        @for (m of messages(); track $index; let last = $last) {
          <div class="bubble" [class.me]="m.role === 'user'" [class.bot]="m.role !== 'user'">
            <p [innerHTML]="format(m.text)"></p>
            @if (m.table) {
              <div class="grid-wrap"><table class="mini">
                <thead><tr>@for (h of m.table[0]; track $index) { <th>{{ h }}</th> }</tr></thead>
                <tbody>@for (r of m.table[1]; track $index) { <tr>@for (c of r; track $index) { <td>{{ c }}</td> }</tr> }</tbody>
              </table></div>
            }
            @if (m.links?.length) {
              <div class="links">@for (l of m.links; track $index) { <a [href]="l[1]" target="_blank" rel="noopener">{{ l[0] }}</a> }</div>
            }
            @if (last && m.choices?.length) {
              <p class="small"><b>🎓 ¿Qué querías hacer?</b></p>
              <div class="links">@for (c of m.choices; track c[1]) { <button class="choice" [disabled]="busy()" (click)="learn(c)">{{ c[0] }}</button> }</div>
            }
            @if (last && m.confirm && pending()) {
              <div class="confirm">
                <button class="primary" [disabled]="busy()" (click)="decide(true)">✅ Sí, guardar</button>
                <button class="secondary" [disabled]="busy()" (click)="decide(false)">✖ No</button>
              </div>
            }
            @if (m.time) { <small class="time">{{ m.time }}</small> }
          </div>
        }
        @if (busy()) { <div class="bubble bot"><p class="muted">Pensando…</p></div> }
      </div>
      @if (error()) { <p class="error">{{ error() }}</p> }
      <div class="composer">
        @if (canTalk) {
          <button class="mic" [class.on]="listening()" (click)="toggleMic()" [attr.aria-label]="listening() ? 'Detener' : 'Hablar'">{{ listening() ? '⏹' : '🎤' }}</button>
        }
        <textarea rows="1" [(ngModel)]="text" placeholder="Escribe o toca 🎤 y habla…" (keydown.enter)="enter($event)"></textarea>
        <button class="send" [disabled]="busy() || !text.trim()" (click)="send()">➤</button>
      </div>
    </section>
  `,
})
export class AssistantPage implements OnDestroy {
  private api = inject(Api);
  protected session = inject(Session);
  protected messages = signal<ChatMessage[]>([]);
  protected pending = signal(false);
  protected busy = signal(false);
  protected error = signal('');
  protected listening = signal(false);
  protected text = '';
  protected canTalk = typeof window !== 'undefined' && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window);
  private list = viewChild<ElementRef<HTMLDivElement>>('list');
  private rec: any = null;
  private base = '';

  constructor() {
    this.api.chatHistory().then((h) => { this.messages.set(h.messages); this.pending.set(h.pending); this.scroll(); })
      .catch((e) => { if (isExpired(e)) this.session.expired(); });
  }

  /** **negrita**, *cursiva* y saltos de línea, con el resto escapado. */
  format(t: string) {
    const esc = (t || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]!);
    return esc.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/(^|[^\w*])\*(?!\s)(.+?)\*(?!\w)/g, '$1<i>$2</i>').replace(/\n/g, '<br>');
  }

  enter(e: Event) {
    const k = e as KeyboardEvent;
    if (!k.shiftKey) { k.preventDefault(); this.send(); }
  }

  async send() {
    const t = this.text.trim();
    if (!t || this.busy()) return;
    this.stopMic();
    this.text = '';
    this.base = '';
    this.messages.update((m) => [...m, { role: 'user', text: t }]);
    await this.call(() => this.api.chatSend(t));
  }

  async decide(yes: boolean) {
    this.messages.update((m) => [...m, { role: 'user', text: yes ? 'Sí, guardar' : 'No' }]);
    this.pending.set(false);
    await this.call(() => (yes ? this.api.chatConfirm() : this.api.chatCancel()));
  }

  async learn(c: [string, string]) {
    this.messages.update((m) => [...m, { role: 'user', text: '👉 Quería: ' + c[0] }]);
    await this.call(() => this.api.chatLearn(c[1]));
  }

  private async call(fn: () => Promise<ChatMessage>) {
    this.busy.set(true);
    this.error.set('');
    this.scroll();
    try {
      const r = await fn();
      this.messages.update((m) => [...m, r]);
      this.pending.set(!!r.confirm);
    } catch (e) {
      if (isExpired(e)) this.session.expired();
      this.error.set(errorText(e));
    } finally {
      this.busy.set(false);
      this.scroll();
    }
  }

  toggleMic() { this.listening() ? this.stopMic() : this.startMic(); }

  private startMic() {
    const SR = (window as any).SpeechRecognition || webkitSpeechRecognition;
    this.base = this.text ? this.text.trim() + ' ' : '';
    this.listening.set(true);
    const rec = new SR();
    rec.lang = 'es-BO';
    rec.interimResults = true;
    rec.continuous = false;
    rec.onresult = (e: any) => {
      let finals = '', interim = '';
      for (const r of e.results) r.isFinal ? (finals += r[0].transcript) : (interim += r[0].transcript);
      if (finals) this.base += finals.trim() + ' ';
      this.text = (this.base + interim).trimStart();
    };
    rec.onerror = (e: any) => {
      if (e.error === 'not-allowed') this.error.set('Permite el micrófono para esta página (candado 🔒 junto a la dirección).');
      if (e.error === 'not-allowed' || e.error === 'network') this.listening.set(false);
    };
    rec.onend = () => { if (this.listening()) { try { rec.start(); } catch { this.listening.set(false); } } };
    this.rec = rec;
    try { rec.start(); } catch { this.listening.set(false); }
  }

  private stopMic() {
    this.listening.set(false);
    try { this.rec?.stop(); } catch { /* ya estaba detenido */ }
  }

  private scroll() { setTimeout(() => { const el = this.list()?.nativeElement; if (el) el.scrollTop = el.scrollHeight; }, 40); }

  ngOnDestroy() { this.stopMic(); }
}
