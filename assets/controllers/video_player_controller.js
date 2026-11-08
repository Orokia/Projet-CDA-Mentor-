import { Controller } from '@hotwired/stimulus';

// Lecteur vidéo de la présentation du formateur.
//
// Amélioration progressive : la balise <video> garde l'attribut « controls »
// dans le HTML. Au chargement, ce contrôleur le retire et affiche la barre
// de contrôle Mentor. Sans JavaScript, les contrôles natifs restent visibles.
//
// Raccourcis (quand un contrôle du lecteur a le focus ; cliquer sur la vidéo
// le donne au bouton lecture-pause) :
//   Espace / K : lecture-pause    ← / → : -5 s / +5 s
//   M : couper le son             F : plein écran
export default class extends Controller {

    static targets = ['video', 'toggle', 'progress', 'current', 'duration', 'mute', 'volume', 'speed', 'fullscreen'];

    static vitesses = [1, 1.25, 1.5, 2, 0.75];

    connect() {
        this.video = this.videoTarget;
        this.video.removeAttribute('controls');
        this.element.classList.add('is-enhanced');

        this.onFullscreenChange = () => this.updateFullscreen();
        document.addEventListener('fullscreenchange', this.onFullscreenChange);

        this.updateDuration();
        this.updateState();
    }

    disconnect() {
        document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        this.video.pause();
    }

    // --- Actions ------------------------------------------------------

    // Clic sur l'image : lecture-pause, puis focus sur le bouton de la barre
    // pour que les raccourcis clavier restent actifs
    clickVideo() {
        this.togglePlay();
        this.toggleTargets.at(-1)?.focus({ preventScroll: true });
    }

    togglePlay() {
        if (this.video.paused || this.video.ended) {
            this.video.play().catch(() => this.element.classList.add('is-error'));
        } else {
            this.video.pause();
        }
    }

    seek() {
        if (Number.isFinite(this.video.duration)) {
            this.video.currentTime = (this.progressTarget.value / 100) * this.video.duration;
        }
    }

    // Appelé avec un nombre (raccourci clavier) ou un événement Stimulus
    // portant data-video-player-skip-param
    skip(valeur) {
        const secondes = typeof valeur === 'number' ? valeur : Number(valeur.params.skip);

        if (Number.isFinite(this.video.duration)) {
            this.video.currentTime = Math.min(Math.max(this.video.currentTime + secondes, 0), this.video.duration);
        }
    }

    toggleMute() {
        this.video.muted = !this.video.muted;
        this.updateVolume();
    }

    changeVolume() {
        this.video.volume = Number(this.volumeTarget.value);
        this.video.muted = this.video.volume === 0;
        this.updateVolume();
    }

    cycleSpeed() {
        const vitesses = this.constructor.vitesses;
        const index = vitesses.indexOf(this.video.playbackRate);

        this.video.playbackRate = vitesses[(index + 1) % vitesses.length];
        this.speedTarget.textContent = `${this.video.playbackRate}×`;
        this.speedTarget.setAttribute('aria-label', `Vitesse de lecture : ${this.video.playbackRate}×`);
    }

    toggleFullscreen() {
        // Le navigateur peut refuser le plein écran (iframe, réglages) : la vidéo
        // reste lisible dans la page, il n'y a rien d'autre à faire.
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        } else if (this.element.requestFullscreen) {
            this.element.requestFullscreen().catch(() => {});
        }
    }

    keydown(event) {
        // Laisser les curseurs gérer leurs flèches, et les boutons leur Espace / Entrée
        if (event.target.matches('input[type="range"]') && event.key.startsWith('Arrow')) {
            return;
        }

        if (event.target.matches('button') && (event.key === ' ' || event.key === 'Enter')) {
            return;
        }

        const raccourcis = {
            ' ': () => this.togglePlay(),
            k: () => this.togglePlay(),
            ArrowLeft: () => this.skip(-5),
            ArrowRight: () => this.skip(5),
            m: () => this.toggleMute(),
            f: () => this.toggleFullscreen(),
        };

        const action = raccourcis[event.key.length === 1 ? event.key.toLowerCase() : event.key];

        if (action) {
            event.preventDefault();
            action();
        }
    }

    // --- Événements de la vidéo ---------------------------------------

    updateState() {
        const enLecture = !this.video.paused && !this.video.ended;

        this.element.classList.toggle('is-playing', enLecture);
        this.element.classList.toggle('has-started', this.video.currentTime > 0 || enLecture);

        this.toggleTargets.forEach((bouton) => {
            bouton.setAttribute('aria-label', enLecture ? 'Mettre en pause' : 'Lire la vidéo');
        });
    }

    updateProgress() {
        const { currentTime, duration } = this.video;

        if (Number.isFinite(duration) && duration > 0) {
            const pourcentage = (currentTime / duration) * 100;

            this.progressTarget.value = pourcentage;
            this.progressTarget.style.setProperty('--progress', `${pourcentage}%`);
            this.progressTarget.setAttribute('aria-valuetext', `${this.format(currentTime)} sur ${this.format(duration)}`);
        }

        this.currentTarget.textContent = this.format(currentTime);
    }

    updateDuration() {
        this.durationTarget.textContent = this.format(this.video.duration);
    }

    updateVolume() {
        const muet = this.video.muted || this.video.volume === 0;

        this.element.classList.toggle('is-muted', muet);
        this.muteTarget.setAttribute('aria-label', muet ? 'Activer le son' : 'Couper le son');
        this.volumeTarget.value = muet ? 0 : this.video.volume;
    }

    updateFullscreen() {
        const pleinEcran = document.fullscreenElement === this.element;

        this.element.classList.toggle('is-fullscreen', pleinEcran);
        this.fullscreenTarget.setAttribute('aria-label', pleinEcran ? 'Quitter le plein écran' : 'Plein écran');
    }

    showError() {
        this.element.classList.add('is-error');
    }

    // --- Outils -------------------------------------------------------

    format(secondes) {
        if (!Number.isFinite(secondes)) {
            return '0:00';
        }

        const total = Math.floor(secondes);
        const minutes = Math.floor(total / 60);

        return `${minutes}:${String(total % 60).padStart(2, '0')}`;
    }
}
