import axios from 'axios';
export const apiClient = axios.create({
    baseURL: 'http://127.0.0.1:4080',
    timeout: 10_000,
    headers: {
        Accept: 'application/json',
    },
});
