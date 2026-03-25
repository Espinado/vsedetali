import { createEcho } from './echo-setup';

const echo = createEcho();

if (echo) {
    window.Echo = echo;
}
