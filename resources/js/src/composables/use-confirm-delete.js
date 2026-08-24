import axios from '../api';
import { useToast } from './use-toast';

/**
 * UI-8: delete buttons that silently did nothing (see DATA-7) eroded user
 * trust. This composable is the one shared implementation of
 * "confirm dialog -> DELETE request -> success/failure toast -> update
 * the list from the server's response" — every module's delete button
 * should call this rather than re-implementing the flow (and rather than
 * the plain browser confirm() some modules used before).
 *
 * Usage:
 *   const { confirmDelete } = useConfirmDelete();
 *   confirmDelete({
 *     url: `/api/clients/${item.id}`,
 *     itemLabel: item.name,
 *     onSuccess: (data) => { rows.value = data; },
 *   });
 */
export function useConfirmDelete() {
    const { showMessage } = useToast();

    const confirmDelete = ({ url, itemLabel = 'this item', onSuccess, onError }) => {
        window.Swal.fire({
            title: 'Delete this record?',
            text: `This will delete ${itemLabel}. This action can be undone by an administrator, but it will immediately disappear from lists.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#e7515a',
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            axios.delete(url)
                .then((response) => {
                    showMessage('Deleted successfully.');
                    if (onSuccess) onSuccess(response.data.data);
                })
                .catch((error) => {
                    const message = error.response?.data?.message || 'Error deleting. Please try again.';
                    showMessage(message, 'error');
                    if (onError) onError(error);
                });
        });
    };

    return { confirmDelete };
}
