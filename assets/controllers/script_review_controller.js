import { Controller } from '@hotwired/stimulus';
import { mountOtioPlayer } from '@survos/otio-bundle/player';

export default class extends Controller {
    static targets = ['stage', 'scene', 'seek', 'status', 'track', 'photos', 'transcript', 'previous', 'next', 'description', 'prompt', 'spec'];
    static values = { url: String };

    async connect() {
        this.abort = new AbortController();
        this.cueIndex = -1;
        try {
            const response = await fetch(this.urlValue, { signal: this.abort.signal });
            if (!response.ok) throw new Error(`Could not load the script (${response.status}).`);
            this.projection = await response.json();
            if (this.abort.signal.aborted) return;
            this.cues = this.projection.review.cues;
            this.scenes = this.projection.review.scenes;
            this.buildNavigation();
            this.player = mountOtioPlayer(this.stageTarget, this.projection);
            this.seekTarget.max = this.projection.durationFrames - 1;
            this.seekTarget.disabled = this.sceneTarget.disabled = false;
            this.update(0);
        } catch (error) {
            if (error.name !== 'AbortError') this.statusTarget.textContent = error.message;
        }
    }

    disconnect() { this.abort?.abort(); this.player?.unmount(); }

    button(text, frame, className = '') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = text;
        button.dataset.frame = frame;
        button.dataset.action = 'click->script-review#jump';
        return button;
    }

    buildNavigation() {
        this.sceneTarget.replaceChildren();
        this.photosTarget.replaceChildren();
        this.trackTarget.replaceChildren();
        this.transcriptTarget.replaceChildren();
        this.sceneButtons = this.scenes.map(scene => {
            this.sceneTarget.add(new Option(`${scene.sequence} · ${scene.heading}`, scene.id));
            const button = this.button('', scene.startFrame, 'rph-scene-thumb');
            button.dataset.scene = scene.id;
            const image = document.createElement('img');
            image.src = scene.image;
            image.alt = scene.heading;
            image.loading = 'lazy';
            const title = document.createElement('strong');
            title.textContent = `${scene.sequence} · ${scene.heading}`;
            const kind = document.createElement('small');
            kind.textContent = scene.placeholder ? 'Blocking sketch' : 'Scene image';
            button.append(image, title, kind);
            this.photosTarget.append(button);
            return button;
        });
        this.rows = [];
        this.segments = [];
        for (const cue of this.cues) {
            const kind = ['dialogue', 'action'].includes(cue.type) ? cue.type : 'direction';
            const row = this.button('', cue.startFrame, `rph-review-cue ${kind}`);
            const label = document.createElement('strong');
            label.textContent = `${cue.sceneNumber} · ${cue.speaker || cue.type}`;
            const text = document.createElement('span');
            text.textContent = cue.text;
            row.append(label, text);
            this.transcriptTarget.append(row);
            this.rows.push(row);
            const segment = this.button('', cue.startFrame, `rph-track-block ${kind}`);
            segment.style.flexGrow = cue.durationFrames;
            segment.title = `${cue.sceneNumber} · ${cue.speaker || cue.type}: ${cue.text}`;
            segment.setAttribute('aria-label', segment.title);
            this.trackTarget.append(segment);
            this.segments.push(segment);
        }
    }

    frame(event) { if (this.projection) this.update(event.detail.frame); }
    update(frame) {
        this.currentFrame = frame;
        this.seekTarget.value = frame;
        const index = this.cues.findLastIndex(cue => cue.startFrame <= frame);
        const cue = this.cues[Math.max(0, index)];
        this.statusTarget.textContent = `${this.time(frame)} / ${this.time(this.projection.durationFrames)} · Scene ${cue.sceneNumber} · ${cue.speaker || cue.type}`;
        if (index === this.cueIndex) return;
        if (this.cueIndex >= 0) {
            this.rows[this.cueIndex].classList.remove('active');
            this.segments[this.cueIndex].classList.remove('active');
        }
        this.cueIndex = Math.max(0, index);
        this.rows[this.cueIndex].classList.add('active');
        this.segments[this.cueIndex].classList.add('active');
        const row = this.rows[this.cueIndex];
        this.transcriptTarget.scrollTop = Math.max(0, row.offsetTop - this.transcriptTarget.offsetTop - this.transcriptTarget.clientHeight / 3);
        this.previousTarget.disabled = this.cueIndex === 0;
        this.nextTarget.disabled = this.cueIndex === this.cues.length - 1;
        this.sceneTarget.value = cue.sceneId;
        this.sceneButtons.forEach(button => button.classList.toggle('active', button.dataset.scene === cue.sceneId));
        const scene = this.scenes.find(scene => scene.id === cue.sceneId);
        this.descriptionTarget.textContent = scene.artwork.placeholderDescription;
        this.promptTarget.textContent = scene.artwork.imagePrompt;
        this.specTarget.href = scene.artwork.specUrl;
    }

    time(frame) { const seconds = Math.floor(frame / this.projection.fps); return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`; }
    go(frame) { this.player?.seekTo(frame); this.update(frame); }
    jump(event) { this.go(Number(event.currentTarget.dataset.frame)); }
    seek(event) { this.go(Number(event.currentTarget.value)); }
    sceneChanged(event) { const scene = this.scenes.find(scene => scene.id === event.currentTarget.value); if (scene) this.go(scene.startFrame); }
    previous() { if (this.cueIndex > 0) this.go(this.cues[this.cueIndex - 1].startFrame); }
    next() { if (this.cueIndex < this.cues.length - 1) this.go(this.cues[this.cueIndex + 1].startFrame); }
    keyboard(event) {
        if (!this.player || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.target.closest('input,textarea,select,[contenteditable="true"]')) return;
        if (event.key === 'ArrowRight') { event.preventDefault(); this.next(); }
        if (event.key === 'ArrowLeft') { event.preventDefault(); this.previous(); }
    }
}
