import axios from '../api';
import { useToast } from './use-toast';

/**
 * FUN-4: server-side exports need the session cookie (SEC-8's Sanctum SPA
 * auth), so a plain `<a href="/api/export/...">` won't authenticate —
 * the browser navigating directly to that URL doesn't carry the same
 * credentials axios attaches. This composable fetches the file via axios
 * (blob response) and then triggers a client-side download from the blob.
 */
export function useFileDownload() {
    const { showMessage } = useToast();

    const downloadFile = (url, filename, params = {}) => {
        return axios.get(url, { params, responseType: 'blob' })
            .then((response) => {
                const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = blobUrl;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(blobUrl);
            })
            .catch(async (error) => {
                // Error responses are JSON, but since we requested a blob,
                // axios hands back a Blob even for the error body — parse
                // it back to text/JSON to surface a real message.
                let message = 'Error generating the export. Please try again.';
                if (error.response?.data instanceof Blob) {
                    try {
                        const text = await error.response.data.text();
                        const parsed = JSON.parse(text);
                        message = parsed.message || message;
                    } catch (e) {
                        // fall through to the default message
                    }
                }
                showMessage(message, 'error');
            });
    };

    return { downloadFile };
}
