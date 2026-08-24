/**
 * UI-4: every module previously duplicated its own showMessage() Swal-toast
 * helper. This is the single shared version — same behaviour, one place
 * to change it.
 */
export function useToast() {
    const showMessage = (msg = '', type = 'success') => {
        const toast = window.Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
        toast.fire({
            icon: type,
            title: msg,
            padding: '12px 20px',
        });
    };

    return { showMessage };
}
