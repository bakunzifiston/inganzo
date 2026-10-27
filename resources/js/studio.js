import {
    ActiveSelection,
    Canvas,
    classRegistry,
    Ellipse,
    FabricImage,
    FabricObject,
    Group,
    Line,
    Path,
    Point,
    Polygon,
    Rect,
    Shadow,
    Textbox,
    Triangle,
    util,
} from 'fabric';

/*
 | Design Studio (Alpine component "designStudio").
 |
 | One canvas unit is one pixel of the exported print file: the artboard is the product
 | print area at `artboard.dpi`, so millimetres convert with mm = px / dpi * 25.4. Zoom and
 | pan only change Fabric's viewport transform. Fabric objects are kept in this closure, never
 | in Alpine's reactive state; the panels read plain snapshots refreshed by sync().
 |
 | Persistence: the document JSON (no image data – images are referenced by assetRef) is
 | autosaved to the server with a revision number for conflict detection, and mirrored to
 | localStorage so unsaved work can be recovered after a crash or closed tab.
*/

const CUSTOM_PROPS = ['id', 'name', 'locked', 'lockAspect', 'assetRef', 'curve'];
FabricObject.customProperties = CUSTOM_PROPS;

const LOCK_FLAGS = ['lockMovementX', 'lockMovementY', 'lockRotation', 'lockScalingX', 'lockScalingY'];
const HISTORY_LIMIT = 80;
const SNAP_SCREEN_PX = 6;
const GRID_MM = 10;

// Images are decoded once per URL and shared, so undo/redo and duplicates are instant.
const elements = new Map();
function loadElement(url) {
    if (! elements.has(url)) {
        elements.set(url, util.loadImage(url).catch((error) => {
            elements.delete(url);
            throw error;
        }));
    }
    return elements.get(url);
}

class StudioImage extends FabricImage {
    static type = 'Image';

    static async fromObject({ filters, resizeFilter, src, crossOrigin, type, ...object }) {
        const element = await loadElement(src);
        return new StudioImage(element, object);
    }
}
classRegistry.setClass(StudioImage);

const uid = () => Math.random().toString(36).slice(2, 12);
const round = (value, digits = 1) => Math.round(value * 10 ** digits) / 10 ** digits;
const isTyping = (target) => target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

export function registerStudio(Alpine) {
    Alpine.data('designStudio', (config) => {
        const board = config.artboard;
        const mmToPx = (mm) => mm / 25.4 * board.dpi;
        const pxToMm = (px) => px / board.dpi * 25.4;
        const ptToPx = (pt) => pt / 72 * board.dpi;
        const pxToPt = (px) => px / board.dpi * 72;
        const safe = mmToPx(board.safeMm);
        const t = config.i18n;

        let canvas = null;
        let clipboard = null;
        let history = [];
        let future = [];
        let historyTimer = null;
        let saveTimer = null;
        let draftTimer = null;
        let syncFrame = null;
        let guides = [];
        let panning = null;
        let spaceDown = false;
        let exporting = false;
        let loading = false;
        let sources = { ...config.sources };
        let background = config.document.data.background ?? null;

        return {
            zoom: 1,
            fitZoom: 1,
            layers: [],
            sel: null,
            selCount: 0,
            status: 'saved',
            statusMessage: '',
            revision: config.document.revision,
            savedAt: config.document.savedAt,
            changeSeq: 0,
            savedSeq: 0,
            canUndo: false,
            canRedo: false,
            showGrid: false,
            snap: true,
            preview: false,
            leftPanel: 'layers',
            mobilePanel: null,
            library: { uploads: [], artworks: [], loaded: false, loading: false },
            uploading: false,
            uploadError: '',
            replaceMode: false,
            warnings: [],
            lowestDpi: null,
            recovery: null,
            exportState: '',
            exportError: '',
            background,
            dragIndex: null,
            dropIndex: null,
            showShortcuts: false,
            fonts: config.fonts,
            board,

            async init() {
                canvas = new Canvas(this.$refs.canvas, {
                    preserveObjectStacking: true,
                    stopContextMenu: true,
                    fireMiddleClick: true,
                    selectionColor: 'rgba(245, 176, 39, 0.12)',
                    selectionBorderColor: '#a16207',
                    uniformScaling: true,
                });
                FabricObject.ownDefaults.borderColor = '#a16207';
                FabricObject.ownDefaults.cornerColor = '#ffffff';
                FabricObject.ownDefaults.cornerStrokeColor = '#a16207';
                FabricObject.ownDefaults.cornerStyle = 'circle';
                FabricObject.ownDefaults.transparentCorners = false;
                FabricObject.ownDefaults.cornerSize = 11;
                FabricObject.ownDefaults.borderScaleFactor = 1.5;

                this.resize();
                new ResizeObserver(() => this.resize()).observe(this.$refs.stage);
                this.bindCanvasEvents();
                this.bindKeyboard();

                await this.restore(JSON.stringify(config.document.data), false);
                history = [this.snapshot()];
                this.fit();
                this.checkDraft();

                // Web fonts arrive later; re-measure text once they have (never blocks editing).
                this.loadFonts(config.fonts).then(() => this.remeasureText());

                window.addEventListener('beforeunload', (event) => {
                    if (this.status !== 'saved') {
                        this.writeDraft();
                        event.preventDefault();
                        event.returnValue = '';
                    }
                });
            },

            /* ------------------------------------------------------------ canvas & view */

            resize() {
                const { clientWidth, clientHeight } = this.$refs.stage;
                if (! canvas || ! clientWidth || ! clientHeight) return;
                canvas.setDimensions({ width: clientWidth, height: clientHeight });
                this.fitZoom = Math.min((clientWidth - 64) / board.width, (clientHeight - 64) / board.height);
                canvas.requestRenderAll();
            },

            fit() {
                this.resize();
                const z = this.fitZoom;
                const { width, height } = canvas;
                canvas.setViewportTransform([z, 0, 0, z, (width - board.width * z) / 2, (height - board.height * z) / 2]);
                this.zoom = z;
            },

            zoomBy(factor, point = null) {
                const z = Math.min(8, Math.max(this.fitZoom / 8, canvas.getZoom() * factor));
                canvas.zoomToPoint(point ?? new Point(canvas.width / 2, canvas.height / 2), z);
                this.zoom = z;
            },

            // 100 % = the design shown at its physical printed size on a typical 96 DPI screen.
            get zoomLabel() {
                return Math.round(this.zoom * board.dpi / 96 * 100) + '%';
            },

            actualSize() {
                const z = 96 / board.dpi;
                canvas.zoomToPoint(new Point(canvas.width / 2, canvas.height / 2), z);
                this.zoom = z;
            },

            bindCanvasEvents() {
                canvas.on('before:render', ({ ctx }) => ctx === canvas.contextContainer && ! exporting && this.drawBoard(ctx));
                canvas.on('after:render', ({ ctx }) => ctx === canvas.contextContainer && ! exporting && this.drawOverlay(ctx));

                canvas.on('object:added', () => this.changed());
                canvas.on('object:removed', () => this.changed());
                canvas.on('object:modified', () => {
                    guides = [];
                    this.changed();
                });
                canvas.on('text:changed', () => this.changed());
                canvas.on('text:editing:exited', (e) => this.applyCurve(e.target));
                canvas.on('object:moving', (e) => {
                    this.snapMove(e.target);
                    this.queueSync();
                });
                canvas.on('object:scaling', () => this.queueSync());
                canvas.on('object:rotating', (e) => {
                    if (e.e.shiftKey) {
                        e.target.set('angle', Math.round(e.target.angle / 15) * 15);
                    }
                    this.queueSync();
                });
                canvas.on('object:resizing', () => this.queueSync());
                canvas.on('selection:created', () => this.sync());
                canvas.on('selection:updated', () => this.sync());
                canvas.on('selection:cleared', () => this.sync());
                canvas.on('mouse:up', () => {
                    if (guides.length) {
                        guides = [];
                        canvas.requestRenderAll();
                    }
                });

                canvas.on('mouse:wheel', ({ e }) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.ctrlKey || e.metaKey) {
                        this.zoomBy(0.999 ** e.deltaY, canvas.getViewportPoint(e));
                    } else {
                        canvas.relativePan(new Point(-e.deltaX, -e.deltaY));
                    }
                });

                // Pan with space + drag or the middle mouse button.
                canvas.on('mouse:down:before', ({ e }) => {
                    if (spaceDown || e.button === 1) {
                        panning = { x: e.clientX, y: e.clientY };
                        canvas.selection = false;
                        canvas.skipTargetFind = true;
                        canvas.setCursor('grabbing');
                    }
                });
                canvas.on('mouse:move', ({ e }) => {
                    if (! panning) return;
                    canvas.relativePan(new Point(e.clientX - panning.x, e.clientY - panning.y));
                    panning = { x: e.clientX, y: e.clientY };
                });
                canvas.on('mouse:up', () => {
                    if (! panning) return;
                    panning = null;
                    canvas.selection = ! this.preview;
                    canvas.skipTargetFind = this.preview || spaceDown;
                });
            },

            // Pasteboard, then the artboard (checkerboard = transparent, or the background colour).
            drawBoard(ctx) {
                const v = canvas.viewportTransform;
                ctx.save();
                ctx.fillStyle = '#e8e9ef';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.transform(...v);
                if (background) {
                    ctx.fillStyle = background;
                    ctx.fillRect(0, 0, board.width, board.height);
                } else {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, board.width, board.height);
                    // Only the visible part of the artboard is tiled.
                    const cell = 12 / v[0];
                    const inv = util.invertTransform(v);
                    const tl = util.transformPoint(new Point(0, 0), inv);
                    const br = util.transformPoint(new Point(canvas.width, canvas.height), inv);
                    const startRow = Math.max(0, Math.floor(tl.y / cell));
                    const endY = Math.min(board.height, br.y);
                    const startCol = Math.max(0, Math.floor(tl.x / cell));
                    const endX = Math.min(board.width, br.x);
                    ctx.fillStyle = '#eef0f4';
                    ctx.beginPath();
                    for (let row = startRow, y = row * cell; y < endY; row++, y += cell) {
                        for (let col = startCol + ((startCol + row) % 2), x = col * cell; x < endX; col += 2, x += cell * 2) {
                            ctx.rect(x, y, Math.min(cell, board.width - x), Math.min(cell, board.height - y));
                        }
                    }
                    ctx.fill();
                }
                ctx.restore();
            },

            // Faded pasteboard over anything outside the print area, guides, grid and snap lines.
            drawOverlay(ctx) {
                const v = canvas.viewportTransform;
                const px = 1 / v[0];
                ctx.save();
                ctx.transform(...v);

                const tl = util.transformPoint(new Point(0, 0), util.invertTransform(v));
                const br = util.transformPoint(new Point(canvas.width, canvas.height), util.invertTransform(v));
                ctx.beginPath();
                ctx.rect(tl.x, tl.y, br.x - tl.x, br.y - tl.y);
                ctx.rect(0, 0, board.width, board.height);
                ctx.fillStyle = this.preview ? '#e8e9ef' : 'rgba(232, 233, 239, 0.82)';
                ctx.fill('evenodd');

                if (! this.preview) {
                    if (this.showGrid) {
                        ctx.beginPath();
                        const step = mmToPx(GRID_MM);
                        for (let x = step; x < board.width; x += step) { ctx.moveTo(x, 0); ctx.lineTo(x, board.height); }
                        for (let y = step; y < board.height; y += step) { ctx.moveTo(0, y); ctx.lineTo(board.width, y); }
                        ctx.strokeStyle = 'rgba(79, 70, 229, 0.16)';
                        ctx.lineWidth = px;
                        ctx.stroke();
                    }

                    ctx.lineWidth = px;
                    ctx.strokeStyle = 'rgba(23, 25, 35, 0.55)';
                    ctx.strokeRect(0, 0, board.width, board.height);

                    ctx.setLineDash([6 * px, 4 * px]);
                    ctx.strokeStyle = 'rgba(14, 116, 144, 0.75)';
                    ctx.strokeRect(safe, safe, board.width - 2 * safe, board.height - 2 * safe);
                    ctx.setLineDash([]);

                    ctx.strokeStyle = '#e11d74';
                    for (const g of guides) {
                        ctx.beginPath();
                        if (g.axis === 'x') { ctx.moveTo(g.at, tl.y); ctx.lineTo(g.at, br.y); } else { ctx.moveTo(tl.x, g.at); ctx.lineTo(br.x, g.at); }
                        ctx.stroke();
                    }
                }
                ctx.restore();
            },

            /* ------------------------------------------------------------ snapping */

            snapMove(target) {
                guides = [];
                if (! this.snap || ! target) return;
                const threshold = SNAP_SCREEN_PX / canvas.getZoom();
                const box = target.getBoundingRect();
                const xs = [0, board.width / 2, board.width, safe, board.width - safe];
                const ys = [0, board.height / 2, board.height, safe, board.height - safe];
                const moving = new Set(target instanceof ActiveSelection ? target.getObjects() : [target]);
                for (const o of canvas.getObjects()) {
                    if (moving.has(o) || ! o.visible) continue;
                    const b = o.getBoundingRect();
                    xs.push(b.left, b.left + b.width / 2, b.left + b.width);
                    ys.push(b.top, b.top + b.height / 2, b.top + b.height);
                }
                const best = (edges, lines) => {
                    let hit = null;
                    for (const edge of edges) {
                        for (const line of lines) {
                            const d = line - edge;
                            if (Math.abs(d) <= threshold && (hit === null || Math.abs(d) < Math.abs(hit.d))) hit = { d, at: line };
                        }
                    }
                    return hit;
                };
                let sx = best([box.left, box.left + box.width / 2, box.left + box.width], xs);
                let sy = best([box.top, box.top + box.height / 2, box.top + box.height], ys);
                if (this.showGrid) {
                    const step = mmToPx(GRID_MM);
                    sx ??= Math.abs(Math.round(box.left / step) * step - box.left) <= threshold ? { d: Math.round(box.left / step) * step - box.left, at: null } : null;
                    sy ??= Math.abs(Math.round(box.top / step) * step - box.top) <= threshold ? { d: Math.round(box.top / step) * step - box.top, at: null } : null;
                }
                if (sx) { target.set('left', target.left + sx.d); if (sx.at !== null) guides.push({ axis: 'x', at: sx.at }); }
                if (sy) { target.set('top', target.top + sy.d); if (sy.at !== null) guides.push({ axis: 'y', at: sy.at }); }
                target.setCoords();
            },

            /* ------------------------------------------------------------ change tracking */

            changed() {
                if (loading) return;
                this.changeSeq++;
                this.status = 'dirty';
                this.sync();
                clearTimeout(historyTimer);
                historyTimer = setTimeout(() => this.pushHistory(), 250);
                clearTimeout(draftTimer);
                draftTimer = setTimeout(() => this.writeDraft(), 600);
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => this.save(), 3000);
            },

            pushHistory() {
                const snap = this.snapshot();
                if (history[history.length - 1] === snap) return;
                history.push(snap);
                if (history.length > HISTORY_LIMIT) history.shift();
                future = [];
                this.canUndo = history.length > 1;
                this.canRedo = false;
            },

            async undo() {
                clearTimeout(historyTimer);
                this.pushHistory();
                if (history.length < 2) return;
                future.push(history.pop());
                await this.restore(history[history.length - 1]);
            },

            async redo() {
                if (! future.length) return;
                const snap = future.pop();
                history.push(snap);
                await this.restore(snap);
            },

            queueSync() {
                if (syncFrame) return;
                syncFrame = requestAnimationFrame(() => {
                    syncFrame = null;
                    this.sync();
                });
            },

            /* ------------------------------------------------------------ serialisation */

            serialize() {
                const strip = (o) => {
                    delete o.src;
                    delete o.crossOrigin;
                    delete o.filters;
                    delete o.resizeFilter;
                    delete o.path;
                    delete o.styles;
                    delete o.clipPath;
                    if (Array.isArray(o.objects)) o.objects.forEach(strip);
                    if (o.type === 'Group' || o.type === 'group') delete o.layoutManager;
                    return o;
                };
                return {
                    objects: canvas.getObjects().map((o) => strip(o.toObject(CUSTOM_PROPS))),
                    background,
                };
            },

            snapshot() {
                return JSON.stringify(this.serialize());
            },

            // Load a document snapshot. Image layers get their URL from the sources map.
            async restore(json, track = true) {
                loading = true;
                const data = JSON.parse(json);
                const selectedIds = canvas.getActiveObjects().map((o) => o.id);
                const withSources = (o) => {
                    if (o.assetRef) o.src = sources[o.assetRef]?.url ?? '';
                    if (Array.isArray(o.objects)) o.objects.forEach(withSources);
                    return o;
                };
                const objects = await util.enlivenObjects((data.objects ?? []).map(withSources)).catch(() => []);
                canvas.discardActiveObject();
                canvas.remove(...canvas.getObjects());
                background = data.background ?? null;
                this.background = background;
                objects.forEach((o) => {
                    this.prepare(o);
                    canvas.add(o);
                });
                objects.forEach((o) => this.applyCurve(o, false));
                const reselect = canvas.getObjects().filter((o) => selectedIds.includes(o.id));
                if (reselect.length === 1) canvas.setActiveObject(reselect[0]);
                canvas.requestRenderAll();
                loading = false;
                if (track) {
                    this.changeSeq++;
                    this.status = 'dirty';
                    clearTimeout(saveTimer);
                    saveTimer = setTimeout(() => this.save(), 1500);
                    this.writeDraft();
                }
                this.canUndo = history.length > 1;
                this.canRedo = future.length > 0;
                this.sync();
            },

            // Per-object editor state that is not part of Fabric's own serialisation.
            prepare(o) {
                o.id ??= uid();
                this.applyLock(o);
                this.applyAspect(o);
                if (o instanceof Group) o.getObjects().forEach((child) => this.applyAspect(child));
            },

            applyLock(o) {
                const locked = !! o.locked;
                LOCK_FLAGS.forEach((flag) => (o[flag] = locked));
                o.hasControls = ! locked;
                o.selectable = ! locked;
                o.evented = ! locked;
                o.hoverCursor = locked ? 'default' : null;
            },

            applyAspect(o) {
                if (o instanceof Textbox) return;
                const free = o.lockAspect === false;
                o.setControlsVisibility({ ml: free, mr: free, mt: free, mb: free });
            },

            /* ------------------------------------------------------------ panels state */

            sync() {
                if (! canvas) return;
                const objects = canvas.getObjects();
                const active = canvas.getActiveObjects();
                const activeSet = new Set(active);
                this.layers = objects.map((o, index) => ({
                    id: o.id,
                    index,
                    name: o.name || this.typeLabel(o),
                    type: this.kind(o),
                    visible: o.visible !== false,
                    locked: !! o.locked,
                    selected: activeSet.has(o),
                    issue: this.issueFor(o),
                })).reverse();
                this.selCount = active.length;
                this.sel = active.length === 1 ? this.describe(active[0]) : (active.length > 1 ? this.describeMany(active) : null);
                this.check();
            },

            kind(o) {
                if (o instanceof Textbox) return 'text';
                if (o instanceof FabricImage) return 'image';
                if (o instanceof Group) return 'group';
                return 'shape';
            },

            typeLabel(o) {
                return { text: t.text, image: t.image, group: t.group, shape: t.shape }[this.kind(o)];
            },

            describe(o) {
                const box = o.getBoundingRect();
                const base = {
                    id: o.id,
                    kind: this.kind(o),
                    type: o.type,
                    name: o.name || '',
                    x: round(pxToMm(box.left)),
                    y: round(pxToMm(box.top)),
                    w: round(pxToMm(o.getScaledWidth())),
                    h: round(pxToMm(o.getScaledHeight())),
                    angle: Math.round(o.angle ?? 0),
                    opacity: Math.round((o.opacity ?? 1) * 100),
                    locked: !! o.locked,
                    visible: o.visible !== false,
                    lockAspect: o.lockAspect !== false,
                    fill: typeof o.fill === 'string' ? o.fill : '',
                    stroke: typeof o.stroke === 'string' ? o.stroke : '',
                    strokeWidth: round(pxToMm(o.strokeWidth ?? 0), 2),
                    shadow: !! o.shadow,
                    shadowColor: o.shadow?.color ?? 'rgba(0,0,0,0.45)',
                    shadowBlur: round(pxToMm(o.shadow?.blur ?? 0)),
                    shadowX: round(pxToMm(o.shadow?.offsetX ?? 0)),
                    shadowY: round(pxToMm(o.shadow?.offsetY ?? 0)),
                    flipX: !! o.flipX,
                    flipY: !! o.flipY,
                };
                if (o instanceof Textbox) {
                    Object.assign(base, {
                        text: o.text,
                        fontFamily: o.fontFamily,
                        fontSize: round(pxToPt(o.fontSize * (o.scaleY ?? 1))),
                        bold: String(o.fontWeight) === 'bold' || Number(o.fontWeight) >= 600,
                        italic: o.fontStyle === 'italic',
                        underline: !! o.underline,
                        textAlign: o.textAlign,
                        charSpacing: Math.round(o.charSpacing ?? 0),
                        lineHeight: round(o.lineHeight ?? 1.16, 2),
                        curve: o.curve ?? 0,
                    });
                }
                if (o instanceof FabricImage) {
                    const source = sources[o.assetRef] ?? { width: o.width, height: o.height, detail: 1 };
                    const sw = source.width;
                    const sh = source.height;
                    Object.assign(base, {
                        sourceName: source.name ?? '',
                        sourceSize: `${sw} × ${sh} px`,
                        cropL: Math.round((o.cropX / sw) * 100),
                        cropT: Math.round((o.cropY / sh) * 100),
                        cropR: Math.round(((sw - o.cropX - o.width) / sw) * 100),
                        cropB: Math.round(((sh - o.cropY - o.height) / sh) * 100),
                        dpi: this.imageDpi(o),
                        maxSharpMm: Math.floor(pxToMm(o.width * board.dpi * (source.detail ?? 1) / config.thresholds.sharp)),
                    });
                }
                if (o instanceof Rect) base.radius = round(pxToMm(o.rx ?? 0));
                return base;
            },

            describeMany(objects) {
                const boxes = objects.map((o) => o.getBoundingRect());
                const left = Math.min(...boxes.map((b) => b.left));
                const top = Math.min(...boxes.map((b) => b.top));
                return {
                    kind: 'multiple',
                    count: objects.length,
                    x: round(pxToMm(left)),
                    y: round(pxToMm(top)),
                    w: round(pxToMm(Math.max(...boxes.map((b) => b.left + b.width)) - left)),
                    h: round(pxToMm(Math.max(...boxes.map((b) => b.top + b.height)) - top)),
                    opacity: 100,
                };
            },

            /* ------------------------------------------------------------ print checks */

            // Real resolution of an image layer at its current size (detail < 1 for studio
            // exports that already contain enlarged photos). Enlarging never adds detail.
            imageDpi(o) {
                const detail = sources[o.assetRef]?.detail ?? 1;
                const s = o.getObjectScaling();
                return Math.floor(board.dpi * detail / Math.max(Math.abs(s.x), Math.abs(s.y)) + 1e-9);
            },

            images(o = null) {
                const list = [];
                const walk = (x) => {
                    if (x instanceof FabricImage) list.push(x);
                    if (x instanceof Group) x.getObjects().forEach(walk);
                };
                (o ? [o] : canvas.getObjects()).forEach(walk);
                return list;
            },

            issueFor(o) {
                if (o.visible === false) return null;
                const dpis = this.images(o).filter((i) => i.visible !== false).map((i) => this.imageDpi(i));
                const dpi = dpis.length ? Math.min(...dpis) : null;
                if (dpi !== null && dpi < config.thresholds.min) return 'fail';
                const b = o.getBoundingRect();
                if (b.left < -0.5 || b.top < -0.5 || b.left + b.width > board.width + 0.5 || b.top + b.height > board.height + 0.5) return 'warn';
                if (dpi !== null && dpi < config.thresholds.sharp) return 'warn';
                if (b.left < safe - 0.5 || b.top < safe - 0.5 || b.left + b.width > board.width - safe + 0.5 || b.top + b.height > board.height - safe + 0.5) return 'edge';
                return null;
            },

            check() {
                const warnings = [];
                let lowest = null;
                for (const o of canvas.getObjects()) {
                    if (o.visible === false) continue;
                    const name = o.name || this.typeLabel(o);
                    const b = o.getBoundingRect();
                    const inside = b.left < board.width && b.top < board.height && b.left + b.width > 0 && b.top + b.height > 0;
                    if (! inside) {
                        warnings.push({ level: 'warn', id: o.id, text: t.offBoard.replace(':name', name) });
                    } else if (b.left < -0.5 || b.top < -0.5 || b.left + b.width > board.width + 0.5 || b.top + b.height > board.height + 0.5) {
                        warnings.push({ level: 'warn', id: o.id, text: t.cutOff.replace(':name', name) });
                    } else if (b.left < safe - 0.5 || b.top < safe - 0.5 || b.left + b.width > board.width - safe + 0.5 || b.top + b.height > board.height - safe + 0.5) {
                        warnings.push({ level: 'info', id: o.id, text: t.nearEdge.replace(':name', name) });
                    }
                    for (const img of this.images(o)) {
                        if (img.visible === false) continue;
                        const dpi = this.imageDpi(img);
                        lowest = lowest === null ? dpi : Math.min(lowest, dpi);
                        const label = img.name || name;
                        if (dpi < config.thresholds.min) {
                            warnings.push({ level: 'fail', id: o.id, text: t.dpiFail.replace(':name', label).replace(':dpi', dpi).replace(':min', config.thresholds.min) });
                        } else if (dpi < config.thresholds.sharp) {
                            warnings.push({ level: 'warn', id: o.id, text: t.dpiWarn.replace(':name', label).replace(':dpi', dpi) });
                        }
                    }
                }
                this.lowestDpi = lowest;
                this.warnings = warnings;
            },

            get blocking() {
                return this.warnings.some((w) => w.level === 'fail');
            },

            /* ------------------------------------------------------------ adding objects */

            place(o, { center = true } = {}) {
                o.id ??= uid();
                if (center) {
                    o.set({ originX: 'center', originY: 'center', left: board.width / 2, top: board.height / 2 });
                }
                this.prepare(o);
                canvas.add(o);
                canvas.setActiveObject(o);
                canvas.requestRenderAll();
            },

            addText() {
                const o = new Textbox(t.sampleText, {
                    width: Math.min(board.width - 2 * safe, mmToPx(160)),
                    fontFamily: 'Bricolage Grotesque',
                    fontSize: ptToPx(48),
                    fontWeight: '700',
                    fill: '#171923',
                    textAlign: 'center',
                    name: t.text,
                    curve: 0,
                    lockAspect: true,
                });
                this.place(o);
            },

            addShape(kind) {
                const size = Math.min(board.width, board.height) * 0.35;
                const style = { fill: '#f5b027', stroke: null, strokeWidth: 0, strokeUniform: true, name: t.shapes[kind], lockAspect: false };
                const shapes = {
                    rect: () => new Rect({ width: size, height: size * 0.7, ...style }),
                    ellipse: () => new Ellipse({ rx: size / 2, ry: size / 2, ...style }),
                    triangle: () => new Triangle({ width: size, height: size * 0.87, ...style }),
                    line: () => new Line([0, 0, size, 0], { ...style, fill: null, stroke: '#171923', strokeWidth: mmToPx(1.5) }),
                    star: () => new Polygon(this.starPoints(size / 2, size / 4.4, 5), style),
                };
                this.place(shapes[kind]());
            },

            starPoints(outer, inner, spikes) {
                const points = [];
                for (let i = 0; i < spikes * 2; i++) {
                    const r = i % 2 ? inner : outer;
                    const a = (Math.PI / spikes) * i - Math.PI / 2;
                    points.push({ x: Math.cos(a) * r, y: Math.sin(a) * r });
                }
                return points;
            },

            // Fit inside the safe area, and no larger than the sharp print size.
            async addImage(source) {
                if (this.replaceMode) return this.replaceImage(source);
                sources[source.ref] = source;
                const element = await loadElement(source.url);
                const o = new StudioImage(element, { assetRef: source.ref, name: source.name, lockAspect: true });
                const fit = Math.min((board.width - 2 * safe) / source.width, (board.height - 2 * safe) / source.height);
                const sharp = board.dpi * (source.detail ?? 1) / config.thresholds.sharp;
                o.scale(Math.min(fit, sharp));
                this.place(o);
                this.mobilePanel = null;
            },

            async replaceImage(source) {
                this.replaceMode = false;
                const old = canvas.getActiveObject();
                if (! (old instanceof FabricImage)) return;
                sources[source.ref] = source;
                const element = await loadElement(source.url);
                const center = old.getCenterPoint();
                const shownWidth = old.getScaledWidth();
                old.setElement(element);
                old.set({ assetRef: source.ref, cropX: 0, cropY: 0, width: source.width, height: source.height, name: source.name });
                old.scale(shownWidth / source.width);
                old.setPositionByOrigin(center, 'center', 'center');
                old.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            async openLibrary(replace = false) {
                this.replaceMode = replace;
                this.leftPanel = 'images';
                this.mobilePanel = 'left';
                if (this.library.loaded || this.library.loading) return;
                this.library.loading = true;
                try {
                    const response = await fetch(config.urls.assets, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    this.library.uploads = data.uploads;
                    this.library.artworks = data.artworks;
                    this.library.loaded = true;
                } finally {
                    this.library.loading = false;
                }
            },

            async upload(event) {
                const file = event.target.files[0];
                event.target.value = '';
                if (! file) return;
                this.uploadError = '';
                if (! ['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
                    this.uploadError = t.uploadType;
                    return;
                }
                this.uploading = true;
                const body = new FormData();
                body.append('file', file);
                try {
                    const response = await fetch(config.urls.upload, { method: 'POST', body, headers: this.headers() });
                    const data = await response.json().catch(() => ({}));
                    if (! response.ok) {
                        this.uploadError = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || t.uploadFailed);
                        return;
                    }
                    this.library.uploads = [data, ...this.library.uploads.filter((u) => u.ref !== data.ref)];
                    await this.addImage(data);
                } catch (e) {
                    this.uploadError = t.uploadFailed;
                } finally {
                    this.uploading = false;
                }
            },

            /* ------------------------------------------------------------ editing selection */

            active() {
                return canvas.getActiveObject();
            },

            set(key, value) {
                const o = this.active();
                if (! o) return;
                const targets = o instanceof ActiveSelection ? o.getObjects() : [o];
                targets.forEach((x) => x.set(key, value));
                if (o instanceof Textbox) o.initDimensions();
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            setName(value) {
                const o = this.active();
                if (! o || o instanceof ActiveSelection) return;
                o.name = value.slice(0, 80);
                this.changed();
            },

            setGeometry(key, mm) {
                const o = this.active();
                const value = parseFloat(mm);
                if (! o || Number.isNaN(value)) return;
                const box = o.getBoundingRect();
                if (key === 'x') o.set('left', o.left + mmToPx(value) - box.left);
                if (key === 'y') o.set('top', o.top + mmToPx(value) - box.top);
                if (key === 'w' && value > 0) {
                    if (o instanceof Textbox) {
                        o.set('width', mmToPx(value) / o.scaleX);
                    } else {
                        const factor = mmToPx(value) / o.getScaledWidth();
                        o.set('scaleX', o.scaleX * factor);
                        if (o.lockAspect !== false || o instanceof ActiveSelection) o.set('scaleY', o.scaleY * factor);
                    }
                }
                if (key === 'h' && value > 0 && ! (o instanceof Textbox)) {
                    const factor = mmToPx(value) / o.getScaledHeight();
                    o.set('scaleY', o.scaleY * factor);
                    if (o.lockAspect !== false || o instanceof ActiveSelection) o.set('scaleX', o.scaleX * factor);
                }
                if (key === 'angle') o.rotate(((value % 360) + 360) % 360);
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            setOpacity(percent) {
                this.set('opacity', Math.max(0, Math.min(100, Number(percent))) / 100);
            },

            setStrokeWidth(mm) {
                this.set('strokeWidth', mmToPx(Math.max(0, Number(mm))));
            },

            setRadius(mm) {
                const r = mmToPx(Math.max(0, Number(mm)));
                const o = this.active();
                if (o instanceof Rect) {
                    o.set({ rx: r, ry: r });
                    canvas.requestRenderAll();
                    this.changed();
                }
            },

            toggleShadow(on) {
                this.set('shadow', on ? new Shadow({ color: 'rgba(0,0,0,0.45)', blur: mmToPx(2), offsetX: mmToPx(1), offsetY: mmToPx(1) }) : null);
            },

            setShadow(key, value) {
                const o = this.active();
                if (! o?.shadow) return;
                const shadow = new Shadow({ ...o.shadow.toObject(), [key]: key === 'color' ? value : mmToPx(Number(value)) });
                this.set('shadow', shadow);
            },

            toggleAspect() {
                const o = this.active();
                if (! o || o instanceof ActiveSelection) return;
                o.lockAspect = o.lockAspect === false;
                this.applyAspect(o);
                canvas.requestRenderAll();
                this.changed();
            },

            flip(axis) {
                const o = this.active();
                if (o) this.set(axis, ! o[axis]);
            },

            rotateBy(degrees) {
                const o = this.active();
                if (! o) return;
                o.rotate((((o.angle ?? 0) + degrees) % 360 + 360) % 360);
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            /* text */

            async setFont(family) {
                await this.loadFonts([family]);
                this.set('fontFamily', family);
                this.applyCurve(this.active());
            },

            setFontSize(pt) {
                const o = this.active();
                if (! (o instanceof Textbox) || ! (Number(pt) > 0)) return;
                o.set({ fontSize: ptToPx(Number(pt)) / (o.scaleY ?? 1) });
                o.initDimensions();
                this.applyCurve(o);
                canvas.requestRenderAll();
                this.changed();
            },

            setText(value) {
                const o = this.active();
                if (! (o instanceof Textbox)) return;
                o.set('text', value.slice(0, 5000));
                o.initDimensions();
                this.applyCurve(o);
                canvas.requestRenderAll();
                this.changed();
            },

            toggleStyle(style) {
                const o = this.active();
                if (! (o instanceof Textbox)) return;
                if (style === 'bold') this.set('fontWeight', this.sel.bold ? 'normal' : '700');
                if (style === 'italic') this.set('fontStyle', this.sel.italic ? 'normal' : 'italic');
                if (style === 'underline') this.set('underline', ! this.sel.underline);
                this.applyCurve(o);
            },

            setCurve(value) {
                const o = this.active();
                if (! (o instanceof Textbox)) return;
                o.curve = Math.max(-100, Math.min(100, Number(value) || 0));
                this.applyCurve(o);
                this.changed();
            },

            // Curved text follows a circular arc whose length matches the text width; the
            // arc is rebuilt from `curve` (-100…100) on load, so only the number is stored.
            applyCurve(o, render = true) {
                if (! (o instanceof Textbox)) return;
                o.set('path', undefined);
                o.initDimensions();
                const curve = o.curve ?? 0;
                if (curve !== 0) {
                    const width = Math.max(...o._textLines.map((_, i) => o.getLineWidth(i)), 1);
                    const theta = Math.abs(curve) / 100 * Math.PI * 1.8;
                    const r = width / theta;
                    const x = r * Math.sin(theta / 2);
                    const y = r - r * Math.cos(theta / 2);
                    const large = theta > Math.PI ? 1 : 0;
                    const d = curve > 0
                        ? `M ${-x} ${y} A ${r} ${r} 0 ${large} 1 ${x} ${y}`
                        : `M ${-x} ${-y} A ${r} ${r} 0 ${large} 0 ${x} ${-y}`;
                    o.set({ pathAlign: 'center', pathStartOffset: 0, pathSide: 'left' });
                    o.set('path', new Path(d, { visible: false, fill: null, stroke: null }));
                    o.initDimensions();
                }
                o.setCoords();
                o.dirty = true;
                if (render) canvas.requestRenderAll();
            },

            /* images */

            setCrop(side, percent) {
                const o = this.active();
                if (! (o instanceof FabricImage)) return;
                const source = sources[o.assetRef] ?? { width: o.getElement().naturalWidth, height: o.getElement().naturalHeight };
                const sel = { L: this.sel.cropL, T: this.sel.cropT, R: this.sel.cropR, B: this.sel.cropB, [side]: Math.max(0, Math.min(95, Number(percent) || 0)) };
                if (sel.L + sel.R > 95 || sel.T + sel.B > 95) return;
                const crop = {
                    cropX: Math.round(source.width * sel.L / 100),
                    cropY: Math.round(source.height * sel.T / 100),
                };
                crop.width = Math.round(source.width * (100 - sel.R) / 100) - crop.cropX;
                crop.height = Math.round(source.height * (100 - sel.B) / 100) - crop.cropY;
                this.cropTo(o, crop);
            },

            // Change the crop window while keeping the visible pixels where they are.
            cropTo(o, crop) {
                const oldCenter = o.getCenterPoint();
                const delta = new Point(
                    (crop.cropX + crop.width / 2) - (o.cropX + o.width / 2),
                    (crop.cropY + crop.height / 2) - (o.cropY + o.height / 2),
                );
                const shift = util.transformPoint(delta, o.calcTransformMatrix(), true);
                o.set(crop);
                o.setPositionByOrigin(oldCenter.add(shift), 'center', 'center');
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            resetCrop() {
                const o = this.active();
                if (! (o instanceof FabricImage)) return;
                const source = sources[o.assetRef];
                this.cropTo(o, { cropX: 0, cropY: 0, width: source.width, height: source.height });
            },

            fitTo(mode) {
                const o = this.active();
                if (! o) return;
                const inset = mode === 'safe' ? safe : 0;
                const w = board.width - 2 * inset;
                const h = board.height - 2 * inset;
                o.rotate(0);
                const factor = mode === 'fill'
                    ? Math.max(w / o.getScaledWidth(), h / o.getScaledHeight())
                    : Math.min(w / o.getScaledWidth(), h / o.getScaledHeight());
                o.set({ scaleX: o.scaleX * factor, scaleY: o.scaleY * factor });
                o.setPositionByOrigin(new Point(board.width / 2, board.height / 2), 'center', 'center');
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            // Largest size that is still sharp (recommended DPI), centred – never enlarges past the file's detail.
            sharpSize() {
                const o = this.active();
                if (! (o instanceof FabricImage)) return;
                const detail = sources[o.assetRef]?.detail ?? 1;
                const scale = board.dpi * detail / config.thresholds.sharp;
                const center = o.getCenterPoint();
                const fit = Math.min(board.width / o.width, board.height / o.height);
                o.scale(Math.min(scale, fit));
                o.setPositionByOrigin(center, 'center', 'center');
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            resetImage() {
                const o = this.active();
                if (! (o instanceof FabricImage)) return;
                const source = sources[o.assetRef];
                o.set({ cropX: 0, cropY: 0, width: source.width, height: source.height, flipX: false, flipY: false, angle: 0, opacity: 1, skewX: 0, skewY: 0, lockAspect: true });
                this.applyAspect(o);
                const fit = Math.min((board.width - 2 * safe) / source.width, (board.height - 2 * safe) / source.height);
                o.scale(Math.min(fit, board.dpi * (source.detail ?? 1) / config.thresholds.sharp));
                o.setPositionByOrigin(new Point(board.width / 2, board.height / 2), 'center', 'center');
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            /* ------------------------------------------------------------ arrange */

            align(where) {
                const o = this.active();
                if (! o) return;
                const many = o instanceof ActiveSelection;
                const targets = many ? o.getObjects() : [o];
                const frame = many ? o.getBoundingRect() : { left: 0, top: 0, width: board.width, height: board.height };
                if (many) {
                    canvas.discardActiveObject();
                }
                for (const x of targets) {
                    const b = x.getBoundingRect();
                    const dx = { left: frame.left - b.left, center: frame.left + frame.width / 2 - (b.left + b.width / 2), right: frame.left + frame.width - (b.left + b.width) }[where] ?? 0;
                    const dy = { top: frame.top - b.top, middle: frame.top + frame.height / 2 - (b.top + b.height / 2), bottom: frame.top + frame.height - (b.top + b.height) }[where] ?? 0;
                    if (x.locked) continue;
                    x.set({ left: x.left + dx, top: x.top + dy });
                    x.setCoords();
                }
                if (many) canvas.setActiveObject(new ActiveSelection(targets, { canvas }));
                canvas.requestRenderAll();
                this.changed();
            },

            distribute(axis) {
                const o = this.active();
                if (! (o instanceof ActiveSelection) || o.getObjects().length < 3) return;
                const targets = o.getObjects();
                canvas.discardActiveObject();
                const key = axis === 'x' ? 'left' : 'top';
                const size = axis === 'x' ? 'width' : 'height';
                const items = targets.map((x) => ({ x, b: x.getBoundingRect() })).sort((a, b) => a.b[key] - b.b[key]);
                const first = items[0].b;
                const last = items[items.length - 1].b;
                const total = items.reduce((sum, i) => sum + i.b[size], 0);
                const gap = (last[key] + last[size] - first[key] - total) / (items.length - 1);
                let cursor = first[key];
                for (const { x, b } of items) {
                    if (! x.locked) {
                        x.set(key, x[key] + cursor - b[key]);
                        x.setCoords();
                    }
                    cursor += b[size] + gap;
                }
                canvas.setActiveObject(new ActiveSelection(targets, { canvas }));
                canvas.requestRenderAll();
                this.changed();
            },

            order(direction) {
                const o = this.active();
                if (! o || o instanceof ActiveSelection) return;
                ({
                    front: () => canvas.bringObjectToFront(o),
                    forward: () => canvas.bringObjectForward(o),
                    backward: () => canvas.sendObjectBackwards(o),
                    back: () => canvas.sendObjectToBack(o),
                })[direction]();
                canvas.requestRenderAll();
                this.changed();
            },

            group() {
                const o = this.active();
                if (! (o instanceof ActiveSelection)) return;
                const objects = o.getObjects().sort((a, b) => canvas.getObjects().indexOf(a) - canvas.getObjects().indexOf(b));
                const index = canvas.getObjects().indexOf(objects[objects.length - 1]);
                canvas.discardActiveObject();
                loading = true;
                canvas.remove(...objects);
                loading = false;
                const group = new Group(objects, { name: t.group, lockAspect: true });
                this.prepare(group);
                canvas.insertAt(Math.max(0, index - objects.length + 1), group);
                canvas.setActiveObject(group);
                canvas.requestRenderAll();
            },

            ungroup() {
                const o = this.active();
                if (! (o instanceof Group) || o instanceof ActiveSelection) return;
                const index = canvas.getObjects().indexOf(o);
                loading = true;
                const items = o.removeAll();
                canvas.remove(o);
                items.forEach((item, i) => {
                    this.prepare(item);
                    canvas.insertAt(index + i, item);
                });
                loading = false;
                canvas.setActiveObject(new ActiveSelection(items, { canvas }));
                canvas.requestRenderAll();
                this.changed();
            },

            /* ------------------------------------------------------------ clipboard */

            async copy() {
                const o = this.active();
                if (o) clipboard = await o.clone(CUSTOM_PROPS);
            },

            async paste(offset = mmToPx(5)) {
                if (! clipboard) return;
                const copy = await clipboard.clone(CUSTOM_PROPS);
                canvas.discardActiveObject();
                copy.set({ left: copy.left + offset, top: copy.top + offset, evented: true });
                const fresh = (x) => {
                    x.id = uid();
                    x.locked = false;
                    this.prepare(x);
                };
                if (copy instanceof ActiveSelection) {
                    copy.canvas = canvas;
                    copy.forEachObject((x) => {
                        fresh(x);
                        canvas.add(x);
                    });
                    copy.setCoords();
                } else {
                    fresh(copy);
                    canvas.add(copy);
                }
                if (copy instanceof Textbox) this.applyCurve(copy);
                clipboard = await copy.clone(CUSTOM_PROPS);
                canvas.setActiveObject(copy);
                canvas.requestRenderAll();
            },

            async duplicate() {
                const keep = clipboard;
                await this.copy();
                await this.paste();
                clipboard = keep;
            },

            remove() {
                const objects = canvas.getActiveObjects().filter((o) => ! o.locked);
                if (! objects.length) return;
                canvas.discardActiveObject();
                canvas.remove(...objects);
                canvas.requestRenderAll();
            },

            nudge(dx, dy) {
                const o = this.active();
                if (! o || o.locked || o.isEditing) return;
                o.set({ left: o.left + dx, top: o.top + dy });
                o.setCoords();
                canvas.requestRenderAll();
                this.changed();
            },

            selectAll() {
                const objects = canvas.getObjects().filter((o) => ! o.locked && o.visible !== false);
                canvas.discardActiveObject();
                if (objects.length === 1) canvas.setActiveObject(objects[0]);
                if (objects.length > 1) canvas.setActiveObject(new ActiveSelection(objects, { canvas }));
                canvas.requestRenderAll();
            },

            /* ------------------------------------------------------------ layers panel */

            byId(id) {
                return canvas.getObjects().find((o) => o.id === id);
            },

            pickLayer(id, event) {
                const o = this.byId(id);
                if (! o) return;
                if (event.shiftKey || event.metaKey || event.ctrlKey) {
                    const current = canvas.getActiveObjects();
                    const next = current.includes(o) ? current.filter((x) => x !== o) : [...current, o];
                    canvas.discardActiveObject();
                    if (next.length === 1) canvas.setActiveObject(next[0]);
                    if (next.length > 1) canvas.setActiveObject(new ActiveSelection(next, { canvas }));
                } else {
                    canvas.setActiveObject(o);
                }
                canvas.requestRenderAll();
                this.sync();
            },

            toggleVisible(id) {
                const o = this.byId(id);
                if (! o) return;
                o.visible = o.visible === false;
                if (! o.visible) canvas.discardActiveObject();
                canvas.requestRenderAll();
                this.changed();
            },

            toggleLock(id = null) {
                const o = id ? this.byId(id) : this.active();
                if (! o || o instanceof ActiveSelection) return;
                o.locked = ! o.locked;
                this.applyLock(o);
                canvas.requestRenderAll();
                this.changed();
            },

            renameLayer(id, name) {
                const o = this.byId(id);
                if (! o) return;
                o.name = String(name).trim().slice(0, 80) || this.typeLabel(o);
                this.changed();
            },

            layerDrop() {
                if (this.dragIndex === null || this.dropIndex === null || this.dragIndex === this.dropIndex) {
                    this.dragIndex = this.dropIndex = null;
                    return;
                }
                const count = canvas.getObjects().length;
                const o = canvas.getObjects()[count - 1 - this.dragIndex];
                canvas.moveObjectTo(o, count - 1 - this.dropIndex);
                this.dragIndex = this.dropIndex = null;
                canvas.requestRenderAll();
                this.changed();
            },

            focusWarning(id) {
                const o = this.byId(id);
                if (o) {
                    canvas.setActiveObject(o);
                    canvas.requestRenderAll();
                    this.sync();
                }
            },

            /* ------------------------------------------------------------ document */

            setBackground(value) {
                background = value || null;
                this.background = background;
                canvas.requestRenderAll();
                this.changed();
            },

            togglePreview() {
                this.preview = ! this.preview;
                canvas.discardActiveObject();
                canvas.selection = ! this.preview;
                canvas.skipTargetFind = this.preview;
                canvas.requestRenderAll();
            },

            toggleGrid() {
                this.showGrid = ! this.showGrid;
                canvas.requestRenderAll();
            },

            async loadFonts(families) {
                if (! document.fonts) return;
                const loads = Promise.all(families.flatMap((family) => ['400', '700'].map((weight) => document.fonts.load(`${weight} 40px "${family}"`).catch(() => null))));
                await Promise.race([loads, new Promise((resolve) => setTimeout(resolve, 4000))]);
            },

            remeasureText() {
                const walk = (o) => {
                    if (o instanceof Textbox) {
                        o.initDimensions();
                        this.applyCurve(o, false);
                    }
                    if (o instanceof Group) o.getObjects().forEach(walk);
                };
                canvas.getObjects().forEach(walk);
                canvas.requestRenderAll();
                this.sync();
            },

            /* ------------------------------------------------------------ keyboard */

            bindKeyboard() {
                window.addEventListener('keyup', (e) => {
                    if (e.code === 'Space') {
                        spaceDown = false;
                        canvas.skipTargetFind = this.preview;
                        canvas.selection = ! this.preview;
                        canvas.setCursor('default');
                    }
                });
                window.addEventListener('keydown', (e) => {
                    if (isTyping(e.target) || this.active()?.isEditing) return;
                    const mod = e.metaKey || e.ctrlKey;
                    const key = e.key.toLowerCase();
                    const step = e.shiftKey ? mmToPx(10) : mmToPx(1);
                    const run = (fn) => {
                        e.preventDefault();
                        fn();
                    };

                    if (e.code === 'Space') return run(() => {
                        spaceDown = true;
                        canvas.skipTargetFind = true;
                        canvas.selection = false;
                        canvas.setCursor('grab');
                    });
                    if (mod && key === 'z') return run(() => (e.shiftKey ? this.redo() : this.undo()));
                    if (mod && key === 'y') return run(() => this.redo());
                    if (mod && key === 's') return run(() => this.save(true));
                    if (mod && key === 'c') return run(() => this.copy());
                    if (mod && key === 'v') return run(() => this.paste());
                    if (mod && key === 'x') return run(async () => { await this.copy(); this.remove(); });
                    if (mod && key === 'd') return run(() => this.duplicate());
                    if (mod && key === 'a') return run(() => this.selectAll());
                    if (mod && key === 'g') return run(() => (e.shiftKey ? this.ungroup() : this.group()));
                    if (mod && (key === '=' || key === '+')) return run(() => this.zoomBy(1.25));
                    if (mod && key === '-') return run(() => this.zoomBy(0.8));
                    if (mod && key === '0') return run(() => this.fit());
                    if (mod && key === '1') return run(() => this.actualSize());
                    if (mod) return;
                    if (key === 'delete' || key === 'backspace') return run(() => this.remove());
                    if (key === 'escape') return run(() => { canvas.discardActiveObject(); canvas.requestRenderAll(); this.preview && this.togglePreview(); });
                    if (key === 'arrowleft') return run(() => this.nudge(-step, 0));
                    if (key === 'arrowright') return run(() => this.nudge(step, 0));
                    if (key === 'arrowup') return run(() => this.nudge(0, -step));
                    if (key === 'arrowdown') return run(() => this.nudge(0, step));
                    if (key === 't') return run(() => this.addText());
                    if (key === 'r') return run(() => this.addShape('rect'));
                    if (key === 'e') return run(() => this.addShape('ellipse'));
                    if (key === 'l') return run(() => this.addShape('line'));
                    if (key === 'i') return run(() => this.openLibrary());
                    if (key === 'p') return run(() => this.togglePreview());
                    if (key === "'") return run(() => this.toggleGrid());
                    if (key === ']') return run(() => this.order(e.shiftKey ? 'front' : 'forward'));
                    if (key === '[') return run(() => this.order(e.shiftKey ? 'back' : 'backward'));
                    if (key === '?') return run(() => (this.showShortcuts = ! this.showShortcuts));
                });
            },

            /* ------------------------------------------------------------ saving */

            headers(json = false) {
                return {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(json ? { 'Content-Type': 'application/json' } : {}),
                };
            },

            async save(manual = false) {
                clearTimeout(saveTimer);
                if (this.status === 'conflict') return false;
                if (this.saving) {
                    this.saveAgain = true;
                    return this.saving;
                }
                if (this.changeSeq === this.savedSeq && this.status === 'saved') return true;

                const seq = this.changeSeq;
                const data = this.serialize();
                this.status = 'saving';
                this.saving = (async () => {
                    try {
                        const response = await fetch(config.urls.save, {
                            method: 'PUT',
                            headers: this.headers(true),
                            body: JSON.stringify({ revision: this.revision, data }),
                        });
                        const body = await response.json().catch(() => ({}));
                        if (response.status === 409) {
                            this.status = 'conflict';
                            this.statusMessage = body.message ?? '';
                            this.writeDraft();
                            return false;
                        }
                        if (! response.ok) {
                            this.status = 'error';
                            this.statusMessage = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message ?? t.saveFailed);
                            return false;
                        }
                        this.revision = body.revision;
                        this.savedAt = body.saved_at;
                        this.savedSeq = seq;
                        this.statusMessage = '';
                        this.status = this.changeSeq === seq ? 'saved' : 'dirty';
                        if (this.status === 'saved') this.clearDraft();
                        return true;
                    } catch (e) {
                        this.status = 'offline';
                        this.statusMessage = t.offline;
                        saveTimer = setTimeout(() => this.save(), 15000);
                        return false;
                    } finally {
                        this.saving = null;
                    }
                })();
                const ok = await this.saving;
                if (this.saveAgain || (ok && this.status === 'dirty' && manual)) {
                    this.saveAgain = false;
                    return this.save(manual);
                }
                if (ok && this.status === 'dirty') saveTimer = setTimeout(() => this.save(), 3000);
                return ok;
            },

            saving: null,
            saveAgain: false,

            get statusLabel() {
                return {
                    saved: t.saved,
                    saving: t.saving,
                    dirty: t.unsaved,
                    error: t.saveError,
                    conflict: t.conflict,
                    offline: t.offline,
                }[this.status];
            },

            writeDraft() {
                try {
                    localStorage.setItem(config.storageKey, JSON.stringify({ revision: this.revision, at: new Date().toISOString(), data: this.serialize() }));
                } catch (e) {
                    // Storage full or blocked: the server autosave still runs.
                }
            },

            clearDraft() {
                try {
                    localStorage.removeItem(config.storageKey);
                } catch (e) {}
            },

            checkDraft() {
                try {
                    const draft = JSON.parse(localStorage.getItem(config.storageKey) || 'null');
                    if (! draft?.data) return;
                    if (draft.revision !== this.revision || JSON.stringify(draft.data) === this.snapshot()) {
                        this.clearDraft();
                        return;
                    }
                    this.recovery = { at: new Date(draft.at).toLocaleString(), data: draft.data };
                } catch (e) {
                    this.clearDraft();
                }
            },

            async recover() {
                const data = this.recovery.data;
                this.recovery = null;
                const missing = [];
                const walk = (o) => {
                    if (o.assetRef && ! sources[o.assetRef]) missing.push(o.assetRef);
                    (o.objects ?? []).forEach(walk);
                };
                data.objects.forEach(walk);
                if (missing.length) {
                    await this.openLibrary();
                    [...this.library.uploads, ...this.library.artworks].forEach((s) => (sources[s.ref] ??= s));
                }
                await this.restore(JSON.stringify(data));
                this.pushHistory();
            },

            discardRecovery() {
                this.recovery = null;
                this.clearDraft();
            },

            /* ------------------------------------------------------------ export */

            // Render the artboard at full print resolution with a transparent background.
            render() {
                const vpt = canvas.viewportTransform.slice();
                const active = canvas.getActiveObject();
                canvas.discardActiveObject();
                exporting = true;
                // Fabric's object cache is size-capped (≈4096 px); render directly at full resolution.
                const cached = [];
                const uncache = (o) => {
                    cached.push([o, o.objectCaching]);
                    o.objectCaching = false;
                    if (o instanceof Group) o.getObjects().forEach(uncache);
                };
                canvas.getObjects().forEach(uncache);
                canvas.backgroundColor = background || '';
                canvas.viewportTransform = [1, 0, 0, 1, 0, 0];
                try {
                    return canvas.toCanvasElement(1, { left: 0, top: 0, width: board.width, height: board.height });
                } finally {
                    canvas.backgroundColor = '';
                    cached.forEach(([o, value]) => {
                        o.objectCaching = value;
                        o.dirty = true;
                    });
                    canvas.setViewportTransform(vpt);
                    exporting = false;
                    if (active) canvas.setActiveObject(active);
                    canvas.requestRenderAll();
                }
            },

            async blob() {
                await document.fonts?.ready;
                const element = this.render();
                return new Promise((resolve, reject) => element.toBlob((b) => (b ? resolve(b) : reject(new Error('render'))), 'image/png'));
            },

            async download() {
                this.exportError = '';
                this.exportState = 'rendering';
                try {
                    const blob = await this.blob();
                    const url = URL.createObjectURL(blob);
                    const a = Object.assign(document.createElement('a'), { href: url, download: `${config.slug}-${board.width}x${board.height}.png` });
                    a.click();
                    setTimeout(() => URL.revokeObjectURL(url), 2000);
                } catch (e) {
                    this.exportError = t.renderFailed;
                } finally {
                    this.exportState = '';
                }
            },

            async exportToProducts() {
                this.exportError = '';
                if (! canvas.getObjects().length) {
                    this.exportError = t.empty;
                    return;
                }
                if (config.published && ! window.confirm(t.confirmUnpublish)) return;
                this.exportState = 'saving';
                if (! (await this.save(true)) || this.status !== 'saved') {
                    this.exportError = this.statusMessage || t.saveFirst;
                    this.exportState = '';
                    return;
                }
                this.exportState = 'rendering';
                try {
                    const blob = await this.blob();
                    this.exportState = 'uploading';
                    const body = new FormData();
                    body.append('file', blob, 'design.png');
                    body.append('revision', this.revision);
                    const response = await fetch(config.urls.export, { method: 'POST', body, headers: this.headers() });
                    const data = await response.json().catch(() => ({}));
                    if (! response.ok) {
                        this.exportError = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || t.exportFailed);
                        this.exportState = '';
                        return;
                    }
                    this.clearDraft();
                    window.location.href = data.redirect;
                } catch (e) {
                    this.exportError = t.exportFailed;
                    this.exportState = '';
                }
            },

            mm(px) {
                return round(pxToMm(px));
            },

            deselect() {
                canvas.discardActiveObject();
                canvas.requestRenderAll();
            },

            // <input type="color"> only accepts #rrggbb.
            hex(value, fallback) {
                if (typeof value !== 'string') return fallback;
                if (/^#[0-9a-f]{6}$/i.test(value)) return value;
                if (/^#[0-9a-f]{3}$/i.test(value)) return '#' + [...value.slice(1)].map((c) => c + c).join('');
                const rgb = value.match(/^rgba?\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
                return rgb ? '#' + rgb.slice(1, 4).map((n) => Number(n).toString(16).padStart(2, '0')).join('') : fallback;
            },
        };
    });
}

document.addEventListener('alpine:init', () => registerStudio(window.Alpine));
