# Design Studio

The Design Studio lets artists compose artwork in the browser from text, shapes and their
own images, then turn it into a print file that goes through the normal product, placement
and moderation flow. It sits alongside the existing upload and placement editor and does
not replace them.

- Create a design: **Studio → Design Studio** or **Designs → Create in Design Studio**.
- Edit an uploaded design: open the design, then **Edit in Design Studio**.

## How it works

| Piece | Where | Notes |
| --- | --- | --- |
| Editor UI | `resources/views/studio/design.blade.php` | Full-screen workspace. Top toolbar, tool rail, layers/images panel, properties panel. |
| Editor logic | `resources/js/studio.js` | Alpine component `designStudio` on Fabric.js 7. A separate Vite entry, so Fabric (~100 kB gzip) loads only on this page. |
| Endpoints | `App\Http\Controllers\Studio\DesignStudioController` | Create, open, save, export, image library, image upload and serving. |
| Persistence and export | `App\Services\Design\DesignStudio` | Revision locking. Export through `ArtworkUploader::addVersion()`. |
| Validation | `App\Services\Design\DesignDocumentValidator` | Allow-list sanitiser. Computes the real photo resolution. |
| Data | `design_documents`, `design_assets`, `artwork_versions.source_dpi_limit` / `design_dpi` | Migration `2026_10_27_130540_create_design_documents_tables`. |

### Artboard and units

The artboard is the chosen product print area at its physical size (millimetres). It is
rendered at `design_dpi`:

- normally 300 DPI;
- raised so the short side is at least 1000 px (the upload minimum), for example on a mug wrap;
- lowered so the long side stays under 6000 px, for very large areas. Browsers cannot reliably
  render larger canvases.

One canvas unit is one pixel of the exported file. The editor shows positions and sizes in
millimetres and font sizes in points. A 6 mm safe area (`DESIGN_SAFE_MARGIN_MM`) is drawn
inside the print area.

### Editable document

`design_documents.data` holds the document, stored as `jsonb`:

- It is a list of Fabric objects (`textbox`, `image`, `rect`, `ellipse`, `circle`, `line`,
  `triangle`, `polygon`, `group`) plus an optional background colour.
- The server keeps only allow-listed properties. It rejects unknown types, gradients,
  filters, clip paths, CSS or `url()` colours, fonts that are not on the list, and
  non-numeric geometry. It caps the document at 300 layers, 3 levels of group nesting and
  5000 characters of text per layer.
- Images are stored as an `assetRef` only. `asset:<id>` is a studio upload and
  `version:<id>` is an artwork file. The reference must belong to the same artist. `src`
  URLs and data URIs are discarded, so no image data is ever stored in the database.
- Cropping is non-destructive: `cropX`, `cropY`, `width` and `height` select a window of
  the original file, which is never modified.
- Curved text is stored as a single `curve` value (−100…100). The arc is rebuilt when the
  document loads.
- `schema_version` versions the format. `revision` increases by one on every save.

### Saving

- **Autosave:** the editor saves 3 seconds after a change, and immediately on ⌘/Ctrl S.
  Each save sends the revision it was based on. If someone saved from another tab or device
  in the meantime, the server answers `409` and the editor shows a "changed elsewhere"
  banner instead of overwriting.
- **Local recovery copy:** every change is also written to `localStorage`. If the tab
  closes or crashes before a save, the next visit offers to restore the copy (only if it is
  based on the current server revision).
- **Leaving the page:** a `beforeunload` warning appears while there are unsaved changes.

### Export

**Export to products** works in these steps:

1. The editor saves the document.
2. The browser renders the artboard at full resolution with a transparent background.
   Fabric's size-capped object cache is bypassed for this render.
3. The browser uploads the PNG with the saved revision.
4. The server checks three things: the revision is current, the file is a real PNG, and its
   pixel size matches the artboard.
5. The server stores the file as a **new artwork version** through
   `ArtworkUploader::addVersion()`. Upload validation, processing, placement re-checks and
   the rule that a published design returns to draft for re-review all apply unchanged.
   Earlier versions and the editable document are kept.

**PNG** downloads the same render locally without creating a version.

## Print resolution: the photo's real detail counts

Text and shapes are vectors and are always rendered sharply at export resolution. Photos
are different.

A photo enlarged in the studio gains pixels in the exported file but no detail. The
validator therefore computes each visible image layer's real resolution:

```
real DPI = design_dpi × detail ÷ displayed scale
```

- `detail` is 1 for uploads.
- For an earlier studio export, `detail` is `source_dpi_limit ÷ design_dpi`.

The lowest value is stored as the version's `source_dpi_limit`, together with its
`design_dpi`. `PlacementValidator::sourceFactor()` then scales every product placement's
effective DPI by `source_dpi_limit ÷ design_dpi`, and `resources/js/editor.js` mirrors this.

**Example:** a 1000 px photo stretched to 2.5× on a 300 DPI artboard has 120 DPI of real
detail. Placed at 80 % width on a T-shirt, it is checked at about 150 DPI, not the 375 DPI
the export's pixel count would suggest.

The existing thresholds (minimum 150, sharp 250, per print area) and publishing rules are
unchanged. Products that fail are never sold.

While editing, each image shows its live DPI with these labels:

- **sharp**
- **printable, softer detail**
- **too low to print**

The editor also offers **Largest sharp size**. Warnings say plainly that enlarging, even
with upscaling tools, cannot restore detail.

## Security

- Every endpoint checks that the signed-in artist owns the artwork, asset or version.
  Others get `404`.
- Editing also requires `ArtworkPolicy::update`, so designs under review, suspended or
  archived cannot be changed.
- Studio uploads are checked by content (`finfo` plus `getimagesize`), limited to
  PNG/JPEG/WebP and 16–8000 px, deduplicated by SHA-256, and stored on the private artwork
  disk. They are served with `nosniff` and private caching.
- Saves are rate-limited (`design-saves`, 40 per minute). Uploads and exports share the
  `uploads` limiter.
- The rendered PNG is produced by the browser. The server verifies its type and size and
  derives the resolution limit from the saved document. The limit can only lower a design's
  DPI, never raise it.

## Keyboard shortcuts

Press **?** in the studio for the full list. Shortcuts are ignored while typing in a field or
editing text on the canvas.

| Action | Shortcut |
| --- | --- |
| Undo / redo | ⌘/Ctrl Z, ⇧⌘/Ctrl Z or ⌘/Ctrl Y |
| Save | ⌘/Ctrl S |
| Copy, paste, cut, duplicate | ⌘/Ctrl C, V, X, D |
| Select all | ⌘/Ctrl A |
| Group, ungroup | ⌘/Ctrl G, ⇧⌘/Ctrl G |
| Delete | Delete / Backspace |
| Move 1 mm (⇧ = 10 mm) | Arrow keys |
| Layer order | `]` and `[` (with ⇧: to front / to back) |
| Zoom | ⌘/Ctrl + / − / 0 (fit) / 1 (actual size), or ⌘/Ctrl + scroll |
| Pan | Space + drag, middle mouse button, or scroll |
| Add text, rectangle, ellipse, line, image | T, R, E, L, I |
| Preview, grid | P, ' |

## Known limits

- Crop is set numerically (left, top, right and bottom percentages); there are no
  on-canvas crop handles yet.
- Text styling applies to the whole text layer, not to individual characters.
- Curved text follows a circular arc; the selection box does not hug the curve exactly.
- The artboard is fixed to the print area chosen at creation. Use **Fit print area** after
  starting a new design to target another product.
- Very large artboards are rendered below 300 DPI; the print check reports the resulting DPI
  honestly.
- Studio uploads cannot be deleted from the image library yet.
