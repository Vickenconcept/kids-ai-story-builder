export const COVER_FRAME_OPTIONS = [
    {
        id: 'classic-leather',
        label: 'Classic leather',
        hint: 'Warm brown leather, antique gold border, tooled grain',
    },
    {
        id: 'minimal-gilt',
        label: 'Minimal art book',
        hint: 'Wide clean margins, hairline silver edge, gallery look',
    },
    {
        id: 'modern-bevel',
        label: 'Industrial metal',
        hint: 'Cool gunmetal steps, sharp bevel, no ornament',
    },
    {
        id: 'ornate-baroque',
        label: 'Baroque treasure',
        hint: 'Thick brass frames, corner rosettes, jewel-box depth',
    },
    {
        id: 'deckle-paper',
        label: 'Handmade cotton',
        hint: 'Soft cream deckle, fibrous tooth, watercolor block vibe',
    },
    { id: 'none', label: 'Plain', hint: 'No frame overlay - only your color, gradient, or image' },
] as const;

export type CoverFrameId = (typeof COVER_FRAME_OPTIONS)[number]['id'];

export const DEFAULT_COVER_FRAME: CoverFrameId = 'classic-leather';

const COVER_FRAME_ID_SET = new Set<string>(COVER_FRAME_OPTIONS.map((o) => o.id));

export function normalizeCoverFrame(f: unknown): CoverFrameId {
    if (typeof f === 'string' && COVER_FRAME_ID_SET.has(f)) {
        return f as CoverFrameId;
    }
    return DEFAULT_COVER_FRAME;
}

export function coverFrameRootClass(frame: unknown): string {
    return `cover-frame-${normalizeCoverFrame(frame)}`;
}
