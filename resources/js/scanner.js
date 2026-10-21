/*
 * Composant Alpine du scanner de QR codes.
 *
 * Bibliothèque : qr-scanner (nimiq), chargée à la demande (import dynamique)
 * uniquement sur la page Scanner. Elle utilise le BarcodeDetector natif quand il
 * existe (Chrome Android), sinon un décodeur dans un Web Worker : l'interface
 * reste fluide sur les téléphones d'entrée de gamme.
 *
 * Le navigateur ne fait que LIRE le QR : la validation est entièrement côté serveur
 * ($wire.scan → ScanService).
 *
 * États : idle → starting → scanning → busy → result
 *         ou : denied | nocamera | insecure | unsupported | error
 */
export default function qrScanner() {
    return {
        state: 'idle',
        scanner: null,
        hasFlash: false,
        flashOn: false,

        async start() {
            if (!window.isSecureContext) {
                this.state = 'insecure';
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia) {
                this.state = 'unsupported';
                return;
            }

            this.state = 'starting';

            // Demande d'autorisation explicite : permet de distinguer « refusé » de « pas de caméra »
            try {
                const probe = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                probe.getTracks().forEach((track) => track.stop());
            } catch (error) {
                this.state = error?.name === 'NotAllowedError' || error?.name === 'SecurityError'
                    ? 'denied'
                    : (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError' ? 'nocamera' : 'error');
                return;
            }

            try {
                const { default: QrScanner } = await import('qr-scanner');

                // Le cadre de visée est dessiné par la page (coins orange + ligne de balayage)
                this.scanner ??= new QrScanner(this.$refs.video, (result) => this.onDecode(result.data), {
                    preferredCamera: 'environment',
                    highlightScanRegion: false,
                    highlightCodeOutline: false,
                    maxScansPerSecond: 5,
                    returnDetailedScanResult: true,
                });

                await this.scanner.start();
                this.state = 'scanning';
                this.hasFlash = await this.scanner.hasFlash().catch(() => false);
            } catch (error) {
                this.state = 'error';
            }
        },

        async onDecode(value) {
            if (this.state !== 'scanning' || !value) {
                return;
            }

            this.state = 'busy';
            this.scanner?.pause();

            try {
                await this.$wire.scan(String(value).slice(0, 500));
            } finally {
                this.state = 'result';
            }
        },

        // Appelé via l'événement Livewire « scan-result »
        onResult(detail) {
            if (navigator.vibrate) {
                navigator.vibrate(detail?.success ? [60, 40, 60] : 200);
            }
        },

        async toggleFlash() {
            try {
                await this.scanner?.toggleFlash();
                this.flashOn = this.scanner?.isFlashOn() ?? false;
            } catch (error) {
                this.hasFlash = false;
            }
        },

        async again() {
            if (!this.scanner) {
                return this.start();
            }

            this.state = 'starting';

            try {
                await this.scanner.start();
                this.state = 'scanning';
            } catch (error) {
                this.state = 'error';
            }
        },

        destroy() {
            this.scanner?.destroy();
            this.scanner = null;
        },
    };
}
