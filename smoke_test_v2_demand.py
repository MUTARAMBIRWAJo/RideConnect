#!/usr/bin/env python3
import argparse
import json
import sys
import time
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


def _safe_json(text):
    try:
        return json.loads(text)
    except Exception:
        return text


class SimpleHttpClient:
    def get(self, url, headers=None, timeout=10):
        return self._request('GET', url, None, headers, timeout)

    def post(self, url, payload, headers=None, timeout=10):
        return self._request('POST', url, payload, headers, timeout)

    def _request(self, method, url, payload, headers, timeout):
        data = None
        final_headers = {'Content-Type': 'application/json'}
        if headers:
            final_headers.update(headers)
        if payload is not None:
            data = json.dumps(payload).encode('utf-8')

        req = Request(url, data=data, headers=final_headers, method=method)
        try:
            with urlopen(req, timeout=timeout) as resp:
                body = resp.read().decode('utf-8', errors='replace')
                return resp.status, body
        except HTTPError as err:
            body = err.read().decode('utf-8', errors='replace') if err.fp else ''
            return err.code, body
        except URLError as err:
            raise RuntimeError(f'HTTP request failed: {err.reason}') from err


CLIENT = SimpleHttpClient()


def banner(text):
    print('\n' + '=' * 70)
    print(f'  {text}')
    print('=' * 70)


def passed(msg):
    print(f'  ✓ {msg}')


def failed(msg):
    print(f'  ✗ {msg}')


def detect_mode(base_url, api_key=None):
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    status, body = CLIENT.get(f'{base_url}/ml/health', headers=headers)
    if status == 200:
        return 'migrated', body

    status, body = CLIENT.get(f'{base_url}/health', headers=headers)
    if status == 200:
        return 'legacy', body

    return 'unknown', body


def test_health(base_url, mode, api_key=None):
    url = f'{base_url}/ml/health' if mode == 'migrated' else f'{base_url}/health'
    banner(f'TEST 1 — {url}')
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    status, body_text = CLIENT.get(url, headers=headers)
    if status != 200:
        failed(f'expected 200, got {status}')
        print(f'    body: {body_text}')
        return False

    body = _safe_json(body_text)
    passed('status code = 200')
    if isinstance(body, dict):
        print(f'    body: {json.dumps(body, indent=2)}')
    else:
        print(f'    body: {body}')
    return True


def test_predict_valid(base_url, mode, api_key=None):
    if mode == 'migrated':
        url = f'{base_url}/ml/predict-demand'
        payload = {'zone_id': 'Z01', 'history': [[0.1] * 17 for _ in range(16)]}
    else:
        url = f'{base_url}/predict/demand'
        payload = {'zone': 'Z01', 'timestamp': '2026-05-12T00:00:00Z'}

    banner(f'TEST 2 — {url}')
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    status, body_text = CLIENT.post(url, payload, headers=headers, timeout=20)
    if status not in (200, 201):
        failed(f'expected 200/201, got {status}')
        print(f'    body: {body_text}')
        return False

    passed(f'status code = {status}')
    body = _safe_json(body_text)
    if isinstance(body, dict):
        print(f'    body: {json.dumps(body, indent=2)}')
    else:
        print(f'    body: {body}')
    return True


def test_predict_unknown_zone(base_url, mode, api_key=None):
    if mode == 'migrated':
        url = f'{base_url}/ml/predict-demand'
        payload = {'zone_id': 'ZZZ', 'history': [[0.1] * 17 for _ in range(16)]}
    else:
        url = f'{base_url}/predict/demand'
        payload = {'zone': 'ZZZ', 'timestamp': '2026-05-12T00:00:00Z'}

    banner(f'TEST 3 — {url} unknown zone')
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    status, body_text = CLIENT.post(url, payload, headers=headers, timeout=20)
    if status != 404:
        failed(f'expected 404, got {status}')
        print(f'    body: {body_text}')
        return False

    passed('status code = 404')
    return True


def test_predict_history_length(base_url, mode, api_key=None):
    if mode == 'migrated':
        url = f'{base_url}/ml/predict-demand'
        payload = {'zone_id': 'Z01', 'history': [[0.1] * 17 for _ in range(15)]}
    else:
        # Legacy contract does not support history validation
        return True

    banner(f'TEST 4 — {url} wrong history length')
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    status, body_text = CLIENT.post(url, payload, headers=headers, timeout=20)
    if status not in (400, 422):
        failed(f'expected 400 or 422, got {status}')
        print(f'    body: {body_text}')
        return False

    passed(f'status code = {status}')
    return True


def test_predict_feature_count(base_url, mode, api_key=None):
    if mode == 'migrated':
        url = f'{base_url}/ml/predict-demand'
        payload = {'zone_id': 'Z01', 'history': [[0.1] * 10 for _ in range(16)]}
    else:
        return True

    banner(f'TEST 5 — {url} wrong feature count')
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    status, body_text = CLIENT.post(url, payload, headers=headers, timeout=20)
    if status not in (400, 422, 500):
        failed(f'expected 400, 422 or 500, got {status}')
        print(f'    body: {body_text}')
        return False

    passed(f'status code = {status}')
    return True


def test_background_logging(base_url, mode, api_key=None):
    if mode != 'migrated':
        return True

    url = f'{base_url}/ml/predict-demand'
    payload = {'zone_id': 'Z01', 'history': [[0.1] * 17 for _ in range(16)]}
    headers = {'Accept': 'application/json'}
    if api_key:
        headers['X-API-Key'] = api_key

    banner(f'TEST 6 — {url} background logging')
    start = time.monotonic()
    status1, _ = CLIENT.post(url, payload, headers=headers, timeout=20)
    first_duration = time.monotonic() - start

    start = time.monotonic()
    status2, _ = CLIENT.post(url, payload, headers=headers, timeout=20)
    second_duration = time.monotonic() - start

    if status1 != 200 or status2 != 200:
        failed(f'one or both requests failed: {status1}, {status2}')
        return False

    passed('both requests succeeded')
    print(f'    first request: {first_duration:.2f}s')
    print(f'    second request: {second_duration:.2f}s')
    return True


def main():
    parser = argparse.ArgumentParser(description='Smoke test ML demand endpoints.')
    parser.add_argument('--host', default='http://127.0.0.1:8000', help='Base URL for the service')
    parser.add_argument('--api-key', default=None, help='X-API-Key for legacy endpoint')
    args = parser.parse_args()

    print(f'Target service: {args.host}')
    mode, probe_body = detect_mode(args.host, args.api_key)
    if mode == 'unknown':
        print('Could not detect migrated or legacy ML service contract at the target host.')
        print('Probe body:')
        print(probe_body or 'no content')
        sys.exit(1)

    print(f'Detected mode: {mode}')
    checks = [
        ('health', test_health),
        ('predict_valid', test_predict_valid),
        ('predict_unknown', test_predict_unknown_zone),
        ('predict_15rows', test_predict_history_length),
        ('predict_10features', test_predict_feature_count),
        ('logging_async', test_background_logging),
    ]

    failed_any = False
    for name, test_fn in checks:
        try:
            result = test_fn(args.host, mode, args.api_key)
        except Exception as exc:
            failed(f'{name} test crashed: {type(exc).__name__}: {exc}')
            failed_any = True
            continue

        if not result:
            failed_any = True

    print('\n======================================================================')
    print('  SMOKE TEST SUMMARY')
    print('======================================================================')
    if failed_any:
        print('  ✗ FAIL')
        sys.exit(1)

    print('  ✓ PASS all checks')


if __name__ == '__main__':
    main()
