import { check, sleep } from 'k6';
import http from 'k6/http';

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

export const options = {
    vus: 1,
    duration: '10s',
    thresholds: {
        http_req_duration: ['p(95)<1000'],
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

export default function () {
    for (const path of ['/', '/login']) {
        const response = http.get(`${baseUrl}${path}`, { tags: { name: path } });
        check(response, { [`${path} returns HTTP 200`]: (result) => result.status === 200 });
    }

    sleep(1);
}
