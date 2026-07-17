// Clipboard helper that works outside secure contexts.
//
// navigator.clipboard only exists on HTTPS/localhost; the local dev host
// (http://kheedma-academy.test via Herd) is plain HTTP, so the modern API is
// undefined there and copying would silently do nothing. Fall back to the
// legacy hidden-textarea + execCommand path in that case.

/** @returns {Promise<boolean>} true when the text landed on the clipboard */
export async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Permission denied or transient failure — try the fallback.
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        textarea.remove();
    }
}
