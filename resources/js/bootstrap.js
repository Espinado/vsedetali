import axios from 'axios';
import { createEcho } from './echo-setup';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const echo = createEcho();

if (echo) {
    window.Echo = echo;
}
