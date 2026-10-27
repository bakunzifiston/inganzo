/*
 | Product mockup editor.
 |
 | Geometry mirrors App\Support\MockupGeometry and App\Services\Artwork\PlacementValidator:
 | offsets and scale are fractions of the print area; the server re-validates and clamps
 | everything on save, so this code only provides immediate feedback.
*/
export function registerEditor(Alpine) {
    Alpine.data('productEditor', (config) => ({
        areas: config.areas,          // [{id, key, label, width_mm, height_mm, min_dpi, recommended_dpi, mockup: {x,y,w,h,view}}]
        image: config.image,          // {width, height, url}
        minScale: config.minScale ?? 0.05,
        placements: config.placements, // {areaId: {enabled, offset_x, offset_y, scale, rotation}}
        colors: config.colors,
        selectedColors: config.selectedColors,
        previewColor: config.previewColor,
        activeArea: config.areas[0]?.id,
        dragging: null,

        get area() {
            return this.areas.find((a) => a.id === this.activeArea);
        },
        get placement() {
            return this.placements[this.activeArea];
        },
        get view() {
            return this.area?.mockup.view ?? 'front';
        },
        get fill() {
            return this.colors.find((c) => c.id === this.previewColor)?.hex ?? '#ffffff';
        },
        quarter(placement) {
            return placement.rotation === 90 || placement.rotation === 270;
        },
        heightPerScale(area, placement) {
            const w = this.quarter(placement) ? this.image.height : this.image.width;
            const h = this.quarter(placement) ? this.image.width : this.image.height;
            return (h / w) / (area.height_mm / area.width_mm);
        },
        maxScale(area, placement) {
            return Math.min(1, 1 / this.heightPerScale(area, placement));
        },
        clamp(area, placement) {
            placement.scale = Math.max(Math.min(this.minScale, this.maxScale(area, placement)), Math.min(placement.scale, this.maxScale(area, placement)));
            const boxH = placement.scale * this.heightPerScale(area, placement);
            placement.offset_x = Math.max(0, Math.min(placement.offset_x, 1 - placement.scale));
            placement.offset_y = Math.max(0, Math.min(placement.offset_y, 1 - boxH));
        },
        box(area) {
            const placement = this.placements[area.id];
            const m = area.mockup;
            const ax = m.x * 1000, ay = m.y * 1000, aw = m.w * 1000, ah = m.h * 1000;
            const w = placement.scale * aw;
            const h = placement.scale * this.heightPerScale(area, placement) * ah;
            const x = ax + placement.offset_x * aw;
            const y = ay + placement.offset_y * ah;
            const cx = x + w / 2, cy = y + h / 2;
            const iw = this.quarter(placement) ? h : w;
            const ih = this.quarter(placement) ? w : h;
            return { ax, ay, aw, ah, x, y, w, h, cx, cy, ix: cx - iw / 2, iy: cy - ih / 2, iw, ih };
        },
        // Pixels across the printed width and height (swapped for quarter turns).
        pixels(area) {
            const p = this.placements[area.id];
            // Studio exports with enlarged photos carry less real detail than pixels (PlacementValidator::sourceFactor()).
            const d = this.image.detail ?? 1;
            return this.quarter(p) ? [this.image.height * d, this.image.width * d] : [this.image.width * d, this.image.height * d];
        },
        // DPI = pixels / (millimetres / 25.4), rounded down like PlacementValidator::dpi().
        dpiFor(pixels, mm) {
            return mm > 0 ? Math.floor(pixels / (mm / 25.4) + 1e-9) : 0;
        },
        measure(area) {
            const p = this.placements[area.id];
            const [pw, ph] = this.pixels(area);
            const widthMm = p.scale * area.width_mm;
            const heightMm = p.scale * this.heightPerScale(area, p) * area.height_mm;
            const dpiX = this.dpiFor(pw, widthMm);
            const dpiY = this.dpiFor(ph, heightMm);
            return { widthMm, heightMm, dpiX, dpiY, dpi: Math.min(dpiX, dpiY) };
        },
        dpi(area) {
            return this.measure(area).dpi;
        },
        quality(area) {
            const dpi = this.dpi(area);
            if (dpi < area.min_dpi) return 'fail';
            if (dpi < area.recommended_dpi) return 'warn';
            return 'pass';
        },
        qualityLabel(area) {
            const dpi = this.dpi(area);
            if (dpi < area.min_dpi) return this.$refs.labels.dataset.fail;
            if (dpi < area.recommended_dpi) return this.$refs.labels.dataset.warn;
            if (dpi >= area.preferred_dpi) return this.$refs.labels.dataset.excellent;
            return this.$refs.labels.dataset.pass;
        },
        // Largest scale that fits the area and reaches `dpi` on both axes.
        maxScaleForDpi(area, dpi) {
            const p = this.placements[area.id];
            const [pw, ph] = this.pixels(area);
            const byWidth = (pw / dpi * 25.4) / area.width_mm;
            const byHeight = (ph / dpi * 25.4) / (this.heightPerScale(area, p) * area.height_mm);
            return Math.min(this.maxScale(area, p), byWidth, byHeight);
        },
        // Largest printed width (mm) that still reaches `dpi`.
        maxWidthAt(area, dpi) {
            return Math.floor(this.maxScaleForDpi(area, dpi) * area.width_mm);
        },
        // Mirrors PlacementValidator::bestPrintScale(): sharp size first, then acceptable size.
        best(area) {
            for (const target of [area.recommended_dpi, area.min_dpi]) {
                const scale = this.maxScaleForDpi(area, target);
                if (scale >= this.minScale) return { scale, target };
            }
            return null;
        },
        requiredPixels(area, dpi) {
            const m = this.measure(area);
            return [Math.ceil(m.widthMm / 25.4 * dpi), Math.ceil(m.heightMm / 25.4 * dpi)];
        },
        fitToQuality() {
            const best = this.best(this.area);
            if (! best) return;
            this.setScale(best.scale);
            this.placement.scale = Math.min(this.placement.scale, best.scale);
            this.clamp(this.area, this.placement);
            this.centerHorizontally();
        },
        startDrag(event) {
            const svg = this.$refs.canvas;
            const point = this.svgPoint(svg, event);
            this.dragging = { x: point.x, y: point.y, ox: this.placement.offset_x, oy: this.placement.offset_y };
            event.target.setPointerCapture?.(event.pointerId);
        },
        drag(event) {
            if (! this.dragging) return;
            const point = this.svgPoint(this.$refs.canvas, event);
            const b = this.box(this.area);
            this.placement.offset_x = this.dragging.ox + (point.x - this.dragging.x) / b.aw;
            this.placement.offset_y = this.dragging.oy + (point.y - this.dragging.y) / b.ah;
            this.clamp(this.area, this.placement);
        },
        endDrag() {
            this.dragging = null;
        },
        svgPoint(svg, event) {
            const pt = svg.createSVGPoint();
            pt.x = event.clientX;
            pt.y = event.clientY;
            return pt.matrixTransform(svg.getScreenCTM().inverse());
        },
        setScale(value) {
            const b = this.box(this.area);
            const centerX = this.placement.offset_x + this.placement.scale / 2;
            const centerY = this.placement.offset_y + (b.h / b.ah) / 2;
            this.placement.scale = parseFloat(value);
            this.clamp(this.area, this.placement);
            const nb = this.box(this.area);
            this.placement.offset_x = centerX - this.placement.scale / 2;
            this.placement.offset_y = centerY - (nb.h / nb.ah) / 2;
            this.clamp(this.area, this.placement);
        },
        nudge(dx, dy) {
            this.placement.offset_x += dx;
            this.placement.offset_y += dy;
            this.clamp(this.area, this.placement);
        },
        centerHorizontally() {
            this.placement.offset_x = (1 - this.placement.scale) / 2;
        },
        centerVertically() {
            const boxH = this.placement.scale * this.heightPerScale(this.area, this.placement);
            this.placement.offset_y = (1 - boxH) / 2;
        },
        fit() {
            this.placement.scale = this.maxScale(this.area, this.placement);
            this.centerHorizontally();
            this.centerVertically();
        },
        rotate() {
            this.placement.rotation = (this.placement.rotation + 90) % 360;
            this.clamp(this.area, this.placement);
        },
        toggleColor(id) {
            this.selectedColors = this.selectedColors.includes(id)
                ? this.selectedColors.filter((c) => c !== id)
                : [...this.selectedColors, id];
        },
        serialized() {
            return JSON.stringify(this.placements);
        },
    }));
}
