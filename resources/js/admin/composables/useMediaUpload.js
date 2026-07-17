import { readonly, ref } from 'vue';
import { media as mediaApi } from '@/api';

/**
 * Sequential multi-file upload to the media library with batch progress.
 *
 * Percent is aggregated over the whole batch weighted by byte size, so one
 * big file among small ones doesn't make the bar jump erratically. State is
 * returned readonly; the only way in is uploadFiles().
 */
export function useMediaUpload() {
    const uploading = ref(false);

    /** { percent, name, index, count } while a batch runs, null otherwise. */
    const progress = ref(null);

    /**
     * @param {FileList|File[]} fileList
     * @returns {Promise<{lastUploadedId: ?number, errorMessage: string}>}
     *   errorMessage stays '' on success and on session expiry (the global
     *   re-login dialog takes over in that case).
     */
    async function uploadFiles(fileList) {
        const files = [...fileList];
        if (!files.length) return { lastUploadedId: null, errorMessage: '' };

        uploading.value = true;
        const totalBytes = files.reduce((sum, file) => sum + file.size, 0) || 1;
        let doneBytes = 0;
        let lastUploadedId = null;
        let errorMessage = '';

        try {
            for (const [index, file] of files.entries()) {
                progress.value = {
                    percent: Math.round((doneBytes / totalBytes) * 100),
                    name: file.name,
                    index: index + 1,
                    count: files.length,
                };
                const { media: uploaded } = await mediaApi.upload(file, (loaded, total) => {
                    const fileFraction = total ? loaded / total : 0;
                    progress.value = {
                        ...progress.value,
                        percent: Math.min(100, Math.round(((doneBytes + fileFraction * file.size) / totalBytes) * 100)),
                    };
                });
                doneBytes += file.size;
                lastUploadedId = uploaded.id;
            }
            progress.value = { ...progress.value, percent: 100 };
        } catch (e) {
            if (!e.sessionExpired) {
                errorMessage = e.errors?.file?.[0] ?? e.message ?? 'Gagal mengunggah file.';
            }
        } finally {
            uploading.value = false;
            progress.value = null;
        }

        return { lastUploadedId, errorMessage };
    }

    return {
        uploading: readonly(uploading),
        progress: readonly(progress),
        uploadFiles,
    };
}
