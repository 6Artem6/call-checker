import axios from 'axios';
import { usePage } from '@inertiajs/inertia-vue3';

const page = usePage();

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['X-CSRF-TOKEN'] = page.props.csrf_token;
