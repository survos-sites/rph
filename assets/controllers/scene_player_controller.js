import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['role', 'speaker', 'text', 'direction', 'counter', 'kind', 'play', 'voice', 'status', 'block', 'audio', 'shot', 'placeholder', 'framing'];
    static values = { payload: Object, shotlist: Boolean };

    connect() {
        this.index = 0;
        this.playing = false;
        this.generation = 0;
        this.audio = this.audioTarget;
        this.render();
    }
    disconnect() { this.stop(); }
    stop() {
        this.generation++;
        this.playing = false;
        clearTimeout(this.timer);
        this.audio?.pause();
        this.audio?.removeAttribute('src');
        if (this.audio) { this.audio.onended = null; this.audio.onerror = null; }
        this.playTarget.textContent = this.shotlistValue ? 'Slideshow' : 'Play scene';
        this.roleTargets.forEach(role => role.classList.remove('is-speaking'));
    }
    render() {
        const item = this.payloadValue.elements[this.index];
        if (!item) { this.textTarget.textContent = 'This scene has no script blocks.'; return; }
        if (this.hasShotTarget) {
            const hasImage = Boolean(item.shot);
            this.shotTarget.hidden = !hasImage;
            if (hasImage) { this.shotTarget.src = item.shot; this.shotTarget.alt = `${item.speaker || 'Stage'} · ${item.text}`; }
            if (this.hasPlaceholderTarget) this.placeholderTarget.hidden = hasImage;
            if (this.hasFramingTarget) this.framingTarget.textContent = item.type === 'dialogue' ? 'Over the shoulder · speaker' : 'Wide / action reference';
        }
        this.speakerTarget.textContent = item.speaker || (item.type === 'parenthetical' ? 'Delivery' : 'Stage direction');
        this.textTarget.textContent = item.text;
        this.kindTarget.textContent = item.type.charAt(0).toUpperCase() + item.type.slice(1);
        this.counterTarget.textContent = `${this.index + 1} / ${this.payloadValue.elements.length}`;
        const before = this.payloadValue.elements.slice(0, this.index).reverse().find(e => ['action', 'parenthetical'].includes(e.type));
        this.directionTarget.textContent = item.type === 'dialogue' && before ? before.text : '';
        this.roleTargets.forEach(role => {
            const active = item.characterId === role.dataset.roleId;
            role.classList.toggle('is-active', active);
            role.classList.remove('is-speaking');
            role.querySelector('[data-role-state]').textContent = active ? (item.type === 'dialogue' ? 'Speaking' : 'Direction') : (role.dataset.roleId.startsWith('extra:') ? 'On stage' : 'Listening');
        });
        this.blockTargets.forEach((block, index) => block.classList.toggle('is-current', index === this.index));
    }
    toggle() {
        if (this.playing) { this.stop(); this.statusTarget.textContent = 'Paused. Play resumes this block.'; return; }
        this.playing = true;
        this.playTarget.textContent = 'Pause';
        this.playBlock();
    }
    playBlock() {
        if (!this.playing) return;
        this.render();
        const item = this.payloadValue.elements[this.index];
        if (!item) { this.stop(); return; }
        const token = ++this.generation;
        const finish = () => { if (token === this.generation && this.playing) this.advance(); };
        if (item.type === 'dialogue' && this.voiceTarget.checked && item.audio) {
            this.statusTarget.textContent = `Playing ${item.speaker}'s local voice.`;
            this.audio.src = item.audio;
            this.audio.onended = finish;
            this.audio.onerror = () => this.audioFailed(token);
            this.roleTargets.filter(r => r.dataset.roleId === item.characterId).forEach(r => r.classList.add('is-speaking'));
            this.audio.play().catch(() => this.audioFailed(token));
        } else {
            this.statusTarget.textContent = item.type === 'dialogue' && this.voiceTarget.checked ? 'No cached voice for this block. Showing dialogue.' : 'Following the script.';
            const seconds = item.type === 'dialogue' ? Math.max(2, item.text.split(/\s+/).length / 2.6) : Math.max(2, Math.min(8, item.text.split(/\s+/).length / 5));
            this.timer = setTimeout(finish, seconds * 1000);
        }
    }
    audioFailed(token) {
        if (token !== this.generation || !this.playing) return;
        this.stop();
        this.statusTarget.textContent = 'Voice could not play. Press Play to retry, or turn Voice off to read the scene.';
    }
    advance() {
        this.audio.pause();
        this.roleTargets.forEach(r => r.classList.remove('is-speaking'));
        if (this.index + 1 >= this.payloadValue.elements.length) {
            this.stop(); this.statusTarget.textContent = 'Scene complete. Choose another scene or restart.'; return;
        }
        this.index++;
        this.playBlock();
    }
    previous() { this.select(this.index - 1); }
    next() { this.select(this.index + 1); }
    restart() { this.select(0); }
    jump(event) { this.select(Number(event.currentTarget.dataset.index)); }
    select(index) {
        const resume = this.playing;
        this.stop();
        this.index = Math.max(0, Math.min(this.payloadValue.elements.length - 1, index));
        this.render();
        this.statusTarget.textContent = 'Ready to play.';
        if (resume) { this.playing = true; this.playTarget.textContent = 'Pause'; this.playBlock(); }
    }
    voiceChanged() {
        if (this.playing) this.select(this.index);
    }
    changeScene(event) { window.location.assign(event.currentTarget.value); }
}
