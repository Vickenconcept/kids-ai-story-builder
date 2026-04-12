/**
 * In-app video playback: prefer a smaller, bandwidth-aware Cloudinary transcode so
 * embedded <video> buffers faster than opening the raw stored MP4 in a new tab.
 *
 * Download / file URLs should keep the original `video_url` from the server.
 */
const PLAYBACK_TRANSFORMS = 'q_auto:low,w_960,c_limit,f_mp4';

function isCloudinaryVideoUploadUrl(url: string): boolean {
    return /^https:\/\/res\.cloudinary\.com\/[^/]+\/video\/upload\//i.test(url);
}

/**
 * Returns a URL suitable for <video src> (Cloudinary-optimized when possible).
 */
export function videoPlaybackSrc(url: string | null | undefined): string | null {
    if (url === null || url === undefined || url === '') {
        return null;
    }

    // Signed / tokenized delivery — do not mutate.
    if (url.includes('/s--')) {
        return url;
    }

    const marker = '/video/upload/';
    const idx = url.indexOf(marker);
    if (idx === -1 || !isCloudinaryVideoUploadUrl(url)) {
        return url;
    }

    const after = url.slice(idx + marker.length);
    if (after.startsWith(`${PLAYBACK_TRANSFORMS}/`)) {
        return url;
    }

    const firstSeg = after.split('/')[0] ?? '';
    // Already has transformation tokens (comma chain or common single tokens).
    if (
        firstSeg.includes(',') ||
        /^(q_|f_|w_|h_|c_|br_|e_|so_|eo_|fl_|vs_|vc_|t_|ar_|dn_|du_)/i.test(firstSeg)
    ) {
        return url;
    }

    return `${url.slice(0, idx + marker.length)}${PLAYBACK_TRANSFORMS}/${after}`;
}
