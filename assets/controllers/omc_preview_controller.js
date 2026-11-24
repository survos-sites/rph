import { Controller } from '@hotwired/stimulus';
import { mountOtioPlayer } from '@survos/otio-bundle/player';

export default class extends Controller {
    static targets = ['stage'];
    static values = { url: String };
    async connect() {
        const abort = this.abort = new AbortController();
        try {
            const response = await fetch(this.urlValue, { signal: abort.signal });
            if (!response.ok) throw new Error('Could not load the production timeline.');
            const projection = await response.json();
            if (!abort.signal.aborted) this.player = mountOtioPlayer(this.stageTarget, projection);
        } catch (error) {
            if (error.name !== 'AbortError') this.stageTarget.textContent = error.message;
        }
    }
    disconnect() { this.abort?.abort(); this.player?.unmount(); }
    seek(event) { this.player?.seekTo(Number(event.currentTarget.dataset.frame)); this.stageTarget.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
}
