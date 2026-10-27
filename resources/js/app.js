import { registerEditor } from './editor';
import { registerCharts } from './charts';

/*
 | Alpine is bundled with Livewire; components are registered on alpine:init.
*/
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    registerEditor(Alpine);
    registerCharts(Alpine);

    // Polls the server-side payment status. The server queries the provider; the
    // browser never decides whether an order is paid.
    Alpine.data('paymentPoller', (url, initial = {}) => ({
        status: initial.status ?? 'pending',
        paid: initial.paid ?? false,
        message: initial.message ?? '',
        attempts: 0,
        timer: null,
        init() {
            if (! this.paid && this.status === 'pending') {
                this.timer = setInterval(() => this.check(), 4000);
            }
        },
        async check() {
            this.attempts++;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (! response.ok) return;
                const data = await response.json();
                this.status = data.status;
                this.paid = data.paid;
                this.message = data.message ?? '';
                if (data.paid || data.status !== 'pending') {
                    clearInterval(this.timer);
                    window.location.reload();
                }
            } catch (e) {
                // Network hiccup: keep polling.
            }
            if (this.attempts > 120) clearInterval(this.timer);
        },
        destroy() {
            clearInterval(this.timer);
        },
    }));

    // Local preview and size feedback before uploading artwork.
    Alpine.data('artworkUpload', (minPixels, maxKb) => ({
        preview: null,
        name: '',
        error: '',
        info: '',
        progress: 0,
        uploading: false,
        pick(event) {
            const file = event.target.files[0];
            this.error = '';
            this.info = '';
            this.preview = null;
            if (! file) return;
            this.name = file.name;
            if (! ['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
                this.error = this.$el.dataset.typeError;
                return;
            }
            if (file.size > maxKb * 1024) {
                this.error = this.$el.dataset.sizeError;
                return;
            }
            const url = URL.createObjectURL(file);
            const image = new Image();
            image.onload = () => {
                this.info = `${image.naturalWidth} × ${image.naturalHeight} px`;
                if (image.naturalWidth < minPixels || image.naturalHeight < minPixels) {
                    this.error = this.$el.dataset.dimensionError;
                }
            };
            image.src = url;
            this.preview = url;
        },
        submit(event) {
            if (this.error) {
                event.preventDefault();
                return;
            }
            const form = event.target;
            const request = new XMLHttpRequest();
            this.uploading = true;
            request.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
            });
            request.addEventListener('load', () => {
                let data = {};
                try { data = JSON.parse(request.responseText); } catch (e) {}
                if (request.status >= 200 && request.status < 300 && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                this.uploading = false;
                this.progress = 0;
                this.error = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || this.$el.dataset.networkError);
            });
            request.addEventListener('error', () => {
                this.uploading = false;
                this.error = this.$el.dataset.networkError;
            });
            request.open(form.method, form.action);
            request.setRequestHeader('Accept', 'application/json');
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            request.send(new FormData(form));
            event.preventDefault();
        },
    }));

    // Product page: keeps colour/size selection in sync with the variant id.
    Alpine.data('variantPicker', (variants, colors, initialColor) => ({
        variants,
        colors,
        color: initialColor,
        size: null,
        init() {
            const sizes = this.sizesForColor();
            this.size = sizes.length ? (sizes.find((s) => s.label === 'M') ?? sizes[0]).id : null;
        },
        sizesForColor() {
            return this.variants.filter((v) => v.color_id === this.color && v.size_id).map((v) => ({ id: v.size_id, label: v.size_label, price: v.price }));
        },
        get variant() {
            return this.variants.find((v) => v.color_id === this.color && (this.size === null ? v.size_id === null : v.size_id === this.size)) ?? null;
        },
        get colorName() {
            return this.colors.find((c) => c.id === this.color)?.name ?? '';
        },
    }));
});
