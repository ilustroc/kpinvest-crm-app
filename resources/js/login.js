import { ready } from './core/dom';
import { initSubmitOnce } from './core/forms';
import './modules/auth/login';

ready(() => {
    initSubmitOnce(document);
});
