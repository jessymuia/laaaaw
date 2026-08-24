import axios from 'axios';

const axiosInstance = axios.create({
    withCredentials: true, // Send the Sanctum session cookie with every request
    withXSRFToken: true,   // Let axios read XSRF-TOKEN cookie and set the header automatically
});

axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Session expired or not authenticated — send the user to login.
            // No token/user data is kept client-side, so there's nothing to clear.
            window.location.href = '/login';
        } else if (error.response && error.response.status === 403) {
            window.location.href = '/pages/error403';
        }
        return Promise.reject(error);
    }
);

// Fetch the CSRF cookie once before any stateful (session-authenticated)
// request is made. Required by Sanctum's SPA cookie authentication.
export const ensureCsrfCookie = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true });

export default axiosInstance;
