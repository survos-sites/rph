import { Controller } from '@hotwired/stimulus';
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { VRMLoaderPlugin, VRMUtils } from '@pixiv/three-vrm';

// Fountain parentheticals ("(ashamed)", "(laughing)") pick the VRM preset expression for the next line.
const MOODS = [
    [/laugh|cheer|happy|joy|smil|delight|grin|giggl/i, 'happy'],
    [/angr|furious|rage|shout|snap|growl|fierce/i, 'angry'],
    [/sad|asham|despair|sob|cry|tear|sorr|grie|miser/i, 'sad'],
    [/surpris|shock|gasp|amaz|startl|astonish|fright/i, 'surprised'],
    [/calm|gentl|soft|measur|thought|quiet|relax|ponder/i, 'relaxed'],
];
const EXPRESSIONS = ['happy', 'angry', 'sad', 'surprised', 'relaxed'];

export function moodFor(text) {
    for (const [pattern, expression] of MOODS) if (pattern.test(text)) return expression;
    return null;
}

export default class extends Controller {
    static targets = ['viewport', 'subtitle', 'play', 'status'];
    static values = { payload: Object };

    async connect() {
        this.index = 0;
        this.playing = false;
        this.generation = 0;
        this.speakerId = null;
        this.audio = new Audio();
        this.levels = new Uint8Array(512);
        this.setupScene();
        try {
            await this.loadCast();
        } catch (error) {
            console.error(error);
            this.statusTarget.textContent = `Could not load characters: ${error.message}`;
            this.playTarget.textContent = 'Unavailable';
            return;
        }
        this.playTarget.disabled = false;
        this.playTarget.textContent = 'Play scene';
        this.statusTarget.textContent = 'Ready to play.';
        this.render();
    }

    disconnect() {
        this.stop();
        cancelAnimationFrame(this.frame);
        this.resizeObserver?.disconnect();
        this.renderer?.dispose();
        this.audioContext?.close();
    }

    setupScene() {
        const viewport = this.viewportTarget;
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setPixelRatio(Math.min(2, window.devicePixelRatio));
        viewport.prepend(this.renderer.domElement);
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(30, 16 / 9, 0.1, 50);
        this.wide = { position: new THREE.Vector3(0, 1.35, 6.2), look: new THREE.Vector3(0, 1.05, 0) };
        this.cameraGoal = this.wide.position.clone();
        this.lookGoal = this.wide.look.clone();
        this.look = this.lookGoal.clone();
        this.camera.position.copy(this.cameraGoal);

        const sun = new THREE.DirectionalLight(0xffffff, 2.4);
        sun.position.set(1.5, 3, 2.5);
        this.scene.add(sun, new THREE.HemisphereLight(0xffffff, 0x7f9f5f, 1.4));
        const ground = new THREE.Mesh(new THREE.CircleGeometry(7, 64), new THREE.MeshLambertMaterial({ color: 0x9cc27a }));
        ground.rotation.x = -Math.PI / 2;
        this.scene.add(ground);

        this.resizeObserver = new ResizeObserver(() => {
            const { clientWidth: width, clientHeight: height } = viewport;
            this.renderer.setSize(width, height, false);
            this.camera.aspect = width / Math.max(1, height);
            this.camera.updateProjectionMatrix();
        });
        this.resizeObserver.observe(viewport);

        this.clock = new THREE.Clock();
        const tick = () => {
            this.frame = requestAnimationFrame(tick);
            this.update(this.clock.getDelta());
            this.renderer.render(this.scene, this.camera);
        };
        tick();
    }

    async loadCast() {
        const loader = new GLTFLoader();
        loader.register(parser => new VRMLoaderPlugin(parser));
        const cast = this.payloadValue.cast.filter(role => role.vrm?.model);
        if (!cast.length) throw new Error('no cast member has a "vrm" entry in data/cast');
        this.actors = new Map();
        const spacing = 1.15;
        await Promise.all(cast.map(async (role, i) => {
            const gltf = await loader.loadAsync(role.vrm.model);
            const vrm = gltf.userData.vrm;
            VRMUtils.removeUnnecessaryVertices(gltf.scene);
            VRMUtils.combineSkeletons?.(gltf.scene);
            VRMUtils.rotateVRM0(vrm);
            vrm.scene.traverse(object => { object.frustumCulled = false; });
            if (role.vrm.tint) this.tint(vrm.scene, role.color);
            const x = (i - (cast.length - 1) / 2) * spacing;
            vrm.scene.position.set(x, 0, 0);
            vrm.scene.rotation.y = -x * 0.18; // turn slightly toward centre stage
            this.restPose(vrm);
            this.scene.add(vrm.scene);
            this.actors.set(role.id, {
                role, vrm, mood: null, weights: {}, phase: Math.random() * 10,
                nextBlink: 1 + Math.random() * 3, blink: 0,
                head: vrm.humanoid.getRawBoneNode('head') ?? vrm.scene,
            });
        }));
    }

    tint(root, hex) {
        const color = new THREE.Color(hex);
        root.traverse(object => {
            const materials = Array.isArray(object.material) ? object.material : [object.material];
            for (const material of materials) {
                if (material?.color) material.color.lerp(color, 0.55);
                if (material?.shadeColorFactor) material.shadeColorFactor.lerp(color.clone().multiplyScalar(0.6), 0.55);
            }
        });
    }

    restPose(vrm) {
        const bone = name => vrm.humanoid.getNormalizedBoneNode(name);
        bone('leftUpperArm')?.rotation.set(0, 0, -1.2);
        bone('rightUpperArm')?.rotation.set(0, 0, 1.2);
        bone('leftLowerArm')?.rotation.set(0, -0.2, 0);
        bone('rightLowerArm')?.rotation.set(0, 0.2, 0);
    }

    // Mouth opening from the voice's loudness; no phoneme data needed.
    voiceLevel() {
        if (!this.analyser || this.audio.paused) return 0;
        this.analyser.getByteTimeDomainData(this.levels);
        let sum = 0;
        for (const value of this.levels) sum += ((value - 128) / 128) ** 2;
        return Math.min(1, Math.max(0, (Math.sqrt(sum / this.levels.length) - 0.015) * 7));
    }

    update(delta) {
        if (!this.actors) return;
        const time = this.clock.elapsedTime;
        const level = this.voiceLevel();
        const speaker = this.actors.get(this.speakerId);
        for (const actor of this.actors.values()) {
            const { vrm } = actor;
            const em = vrm.expressionManager;
            const ease = 1 - Math.exp(-delta * 8);
            for (const name of EXPRESSIONS) {
                actor.weights[name] = THREE.MathUtils.lerp(actor.weights[name] ?? 0, actor.mood === name ? 1 : 0, ease);
                em?.setValue(name, actor.weights[name]);
            }
            actor.nextBlink -= delta;
            if (actor.nextBlink <= 0) { actor.blink = 0.16; actor.nextBlink = 2 + Math.random() * 4; }
            actor.blink = Math.max(0, actor.blink - delta);
            em?.setValue('blink', actor.blink > 0 ? Math.sin((actor.blink / 0.16) * Math.PI) : 0);
            const talking = actor === speaker ? level : 0;
            actor.mouth = THREE.MathUtils.lerp(actor.mouth ?? 0, talking, 1 - Math.exp(-delta * 25));
            em?.setValue('aa', actor.mouth * (0.7 + 0.3 * Math.sin(time * 17)));
            em?.setValue('oh', actor.mouth * 0.35 * (0.5 + 0.5 * Math.sin(time * 11 + 1)));
            if (vrm.lookAt) vrm.lookAt.target = actor === speaker || !speaker ? this.camera : speaker.head;
            const spine = vrm.humanoid.getNormalizedBoneNode('spine');
            if (spine) spine.rotation.x = Math.sin(time * 1.6 + actor.phase) * 0.015;
            const neck = vrm.humanoid.getNormalizedBoneNode('neck');
            if (neck) neck.rotation.x = actor === speaker ? Math.sin(time * 5) * 0.04 * actor.mouth : 0;
            vrm.update(Math.min(0.1, delta)); // spring bones misbehave on long steps; easing above uses real time
        }
        const ease = 1 - Math.exp(-delta * 2.5);
        this.camera.position.lerp(this.cameraGoal, ease);
        this.look.lerp(this.lookGoal, ease);
        this.camera.lookAt(this.look);
    }

    frameOn(actor) {
        if (!actor) {
            this.cameraGoal.copy(this.wide.position);
            this.lookGoal.copy(this.wide.look);
            return;
        }
        const head = actor.head.getWorldPosition(new THREE.Vector3());
        const facing = new THREE.Vector3(0, 0, 1).applyQuaternion(actor.vrm.scene.quaternion);
        this.lookGoal.copy(head).add(new THREE.Vector3(0, -0.12, 0));
        this.cameraGoal.copy(head).addScaledVector(facing, 2.4).add(new THREE.Vector3(0.35, 0.05, 0));
    }

    render() {
        const elements = this.payloadValue.elements;
        const item = elements[this.index];
        if (!item) return;
        const actor = item.type === 'dialogue' ? this.actors?.get(item.characterId) : null;
        this.speakerId = actor ? item.characterId : null;
        if (actor) {
            const previous = elements[this.index - 1];
            actor.mood = previous?.type === 'parenthetical' ? moodFor(previous.text) : null;
        }
        this.frameOn(actor);
        const subtitle = this.subtitleTarget;
        subtitle.replaceChildren();
        if (item.type === 'dialogue') {
            const name = document.createElement('strong');
            name.textContent = item.speaker ?? '';
            subtitle.append(name, item.text);
        } else {
            const direction = document.createElement('em');
            direction.textContent = item.text;
            subtitle.append(direction);
        }
    }

    ensureAudioGraph() {
        if (this.audioContext) { this.audioContext.resume(); return; }
        // Must be created from a user gesture (the Play button).
        this.audioContext = new AudioContext();
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 512;
        this.audioContext.createMediaElementSource(this.audio).connect(this.analyser);
        this.analyser.connect(this.audioContext.destination);
    }

    toggle() {
        if (this.playing) { this.stop(); this.statusTarget.textContent = 'Paused.'; return; }
        this.ensureAudioGraph();
        this.playing = true;
        this.playTarget.textContent = 'Pause';
        this.playBlock();
    }

    stop() {
        this.generation++;
        this.playing = false;
        clearTimeout(this.timer);
        this.audio.pause();
        this.audio.onended = this.audio.onerror = null;
        if (this.hasPlayTarget && !this.playTarget.disabled) this.playTarget.textContent = 'Play scene';
    }

    playBlock() {
        if (!this.playing) return;
        this.render();
        const item = this.payloadValue.elements[this.index];
        const token = ++this.generation;
        const finish = () => { if (token === this.generation && this.playing) this.advance(); };
        if (item.type === 'dialogue' && item.audio) {
            this.statusTarget.textContent = `${item.speaker} speaking${this.actors.get(item.characterId)?.mood ? ` (${this.actors.get(item.characterId).mood})` : ''}.`;
            this.audio.src = item.audio;
            this.audio.onended = () => setTimeout(finish, 350);
            this.audio.onerror = finish;
            this.audio.play().catch(finish);
        } else {
            const words = item.text.split(/\s+/).length;
            const seconds = item.type === 'dialogue' ? Math.max(2, words / 2.6) : item.type === 'parenthetical' ? 1.2 : Math.max(2, Math.min(7, words / 4));
            this.statusTarget.textContent = item.type === 'dialogue' ? 'No cached voice for this line.' : 'Stage direction.';
            this.timer = setTimeout(finish, seconds * 1000);
        }
    }

    advance() {
        if (this.index + 1 >= this.payloadValue.elements.length) {
            this.stop();
            this.speakerId = null;
            this.frameOn(null);
            this.statusTarget.textContent = 'Scene complete.';
            return;
        }
        this.index++;
        this.playBlock();
    }

    select(index) {
        const resume = this.playing;
        this.stop();
        this.index = Math.max(0, Math.min(this.payloadValue.elements.length - 1, index));
        this.render();
        if (resume) { this.playing = true; this.playTarget.textContent = 'Pause'; this.playBlock(); }
    }

    previous() { this.select(this.index - 1); }
    next() { this.select(this.index + 1); }
    restart() { this.select(0); }
    changeScene(event) { window.location.assign(event.currentTarget.value); }
}
