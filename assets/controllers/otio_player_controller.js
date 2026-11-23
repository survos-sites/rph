import { Controller } from '@hotwired/stimulus';
import { mountOtioPlayer } from '@survos/otio-bundle/player';

// Mounts the survos/otio-bundle Remotion player on a playback projection URL.
export default class extends Controller {
    static values = { url: String };

    async connect() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) throw new Error(`Timeline unavailable (${response.status}).`);
            this.element.replaceChildren();
            this.player = mountOtioPlayer(this.element, await response.json());
        } catch (error) {
            this.element.textContent = error.message;
        }
    }

    disconnect() {
        this.player?.unmount();
    }
}
