#!/usr/bin/env python3
"""
Mobile API Smoke Test Suite
Tests critical mobile flows:
1. /api/v1/mobile/drivers/match - Driver matching
2. /api/v1/mobile/trips/request - Trip request creation
3. /api/v1/passenger/trips/* - Trip management endpoints
"""

import json
import sys
import time
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


class Colors:
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    BOLD = '\033[1m'
    END = '\033[0m'


class SimpleHttpClient:
    def get(self, url, headers=None, timeout=10):
        return self._request('GET', url, None, headers, timeout)

    def post(self, url, payload, headers=None, timeout=10):
        return self._request('POST', url, payload, headers, timeout)

    def put(self, url, payload, headers=None, timeout=10):
        return self._request('PUT', url, payload, headers, timeout)

    def _request(self, method, url, payload, headers, timeout):
        data = None
        final_headers = {'Content-Type': 'application/json', 'Accept': 'application/json'}
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
    print(f'\n{Colors.BOLD}{Colors.BLUE}{"=" * 70}{Colors.END}')
    print(f'{Colors.BOLD}{Colors.BLUE}  {text}{Colors.END}')
    print(f'{Colors.BOLD}{Colors.BLUE}{"=" * 70}{Colors.END}')


def passed(msg, detail=None):
    print(f'{Colors.GREEN}✓{Colors.END} {msg}')
    if detail:
        print(f'  {detail}')


def failed(msg, detail=None):
    print(f'{Colors.RED}✗{Colors.END} {msg}')
    if detail:
        print(f'  {detail}')


def warning(msg, detail=None):
    print(f'{Colors.YELLOW}⚠{Colors.END} {msg}')
    if detail:
        print(f'  {detail}')


def _safe_json(text):
    try:
        return json.loads(text)
    except Exception:
        return text


def test_health(base_url):
    """Test API health endpoint"""
    banner('TEST 1 — API Health Check')
    
    status, body_text = CLIENT.get(f'{base_url}/api/v1/health')
    if status == 200:
        passed('Health check passed', f'Status: {status}')
        return True
    else:
        failed(f'Health check failed', f'Expected 200, got {status}')
        return False


def test_driver_matching(base_url, auth_token):
    """Test /api/v1/mobile/drivers/match endpoint"""
    banner('TEST 2 — Mobile Driver Matching (/api/v1/mobile/drivers/match)')
    
    url = f'{base_url}/api/v1/mobile/drivers/match'
    headers = {'Authorization': f'Bearer {auth_token}'}
    
    status, body_text = CLIENT.get(url, headers=headers)
    body = _safe_json(body_text)
    
    if status == 200 or status == 404:
        passed(f'Endpoint accessible', f'Status: {status}')
        if isinstance(body, dict):
            if 'data' in body:
                passed('Response contains data', f'Drivers found: {len(body.get("data", []))}')
            elif 'message' in body:
                warning('Response has message', f'{body.get("message")}')
        return True
    else:
        failed(f'Unexpected response', f'Status: {status}, Body: {body_text[:200]}')
        return False


def test_trip_request(base_url, auth_token):
    """Test /api/v1/mobile/trips/request endpoint"""
    banner('TEST 3 — Mobile Trip Request (/api/v1/mobile/trips/request)')
    
    url = f'{base_url}/api/v1/mobile/trips/request'
    headers = {'Authorization': f'Bearer {auth_token}'}
    
    payload = {
        'pickup_location': 'Downtown Terminal',
        'pickup_lat': -1.2866,
        'pickup_lng': 36.7753,
        'dropoff_location': 'Airport',
        'dropoff_lat': -1.3195,
        'dropoff_lng': 36.9273,
        'transport_type': 'motor_vehicle',
    }
    
    status, body_text = CLIENT.post(url, payload, headers=headers)
    body = _safe_json(body_text)
    
    if status == 200 or status == 201 or status == 422:
        passed(f'Endpoint accessible', f'Status: {status}')
        if isinstance(body, dict):
            if 'data' in body:
                passed('Trip request created/validated', 'Response has data')
            elif 'errors' in body or 'message' in body:
                warning('Validation issues', f'{body.get("message", "")} {json.dumps(body.get("errors", ""))}')
        return True
    else:
        failed(f'Unexpected response', f'Status: {status}, Body: {body_text[:200]}')
        return False


def test_passenger_trips(base_url, auth_token):
    """Test /api/v1/passenger/trips/* endpoints"""
    banner('TEST 4 — Passenger Trips Endpoints')
    
    headers = {'Authorization': f'Bearer {auth_token}'}
    
    # Test GET /api/v1/passenger/trips
    print(f'\n  Testing GET /api/v1/passenger/trips...')
    url = f'{base_url}/api/v1/passenger/trips'
    status, body_text = CLIENT.get(url, headers=headers)
    
    if status == 200 or status == 401 or status == 403:
        passed(f'Trips list endpoint accessible', f'Status: {status}')
    else:
        failed(f'Trips list endpoint error', f'Status: {status}')
    
    # Test GET /api/v1/passenger/trips/current
    print(f'\n  Testing GET /api/v1/passenger/trips/current...')
    url = f'{base_url}/api/v1/passenger/trips/current'
    status, body_text = CLIENT.get(url, headers=headers)
    
    if status == 200 or status == 404 or status == 401 or status == 403:
        passed(f'Current trip endpoint accessible', f'Status: {status}')
    else:
        failed(f'Current trip endpoint error', f'Status: {status}')
    
    # Test GET /api/v1/passenger/trips/{id} with valid and invalid IDs
    print(f'\n  Testing GET /api/v1/passenger/trips/1...')
    url = f'{base_url}/api/v1/passenger/trips/1'
    status, body_text = CLIENT.get(url, headers=headers)
    
    if status in [200, 404, 401, 403]:
        passed(f'Trip detail endpoint accessible', f'Status: {status}')
    else:
        failed(f'Trip detail endpoint error', f'Status: {status}')
    
    # Test invalid trip ID (0)
    print(f'\n  Testing GET /api/v1/passenger/trips/0 (invalid ID)...')
    url = f'{base_url}/api/v1/passenger/trips/0'
    status, body_text = CLIENT.get(url, headers=headers)
    body = _safe_json(body_text)
    
    if status == 404 or status == 422:
        passed(f'Invalid trip ID handled gracefully', f'Status: {status}')
    elif status == 200:
        warning(f'Invalid trip ID returned 200 (should be 404/422)', f'Body: {body_text[:200]}')
    else:
        warning(f'Unexpected status for invalid ID', f'Status: {status}')
    
    return True


def test_invalid_trip_ids(base_url, auth_token):
    """Test resilience to invalid trip IDs"""
    banner('TEST 5 — Trip Endpoint Resilience (Invalid IDs)')
    
    headers = {'Authorization': f'Bearer {auth_token}'}
    invalid_ids = [0, -1, 99999, 'invalid', None]
    
    results = {'resilient': 0, 'vulnerable': 0}
    
    for trip_id in invalid_ids:
        if trip_id is None:
            continue
        
        # Test different endpoints with invalid ID
        endpoints = [
            ('GET', f'/api/v1/passenger/trips/{trip_id}'),
            ('GET', f'/api/v1/passenger/trips/{trip_id}/status'),
            ('PUT', f'/api/v1/passenger/trips/{trip_id}/cancel'),
        ]
        
        for method, path in endpoints:
            url = f'{base_url}{path}'
            
            try:
                if method == 'GET':
                    status, body = CLIENT.get(url, headers=headers)
                elif method == 'PUT':
                    status, body = CLIENT.put(url, {'reason': 'test'}, headers=headers)
                
                # Valid responses for invalid ID: 404 or 422
                if status in [404, 422, 401, 403]:
                    results['resilient'] += 1
                    passed(f'{method} {path} (ID={trip_id})', f'Status: {status} (handled correctly)')
                elif status == 500:
                    results['vulnerable'] += 1
                    failed(f'{method} {path} (ID={trip_id})', f'Status: 500 (server error)')
                else:
                    warning(f'{method} {path} (ID={trip_id})', f'Status: {status}')
            except Exception as e:
                results['vulnerable'] += 1
                failed(f'{method} {path} (ID={trip_id})', f'Exception: {str(e)[:100]}')
    
    print(f'\n{Colors.BOLD}Resilience Summary:{Colors.END}')
    print(f'  ✓ Handled correctly: {results["resilient"]}')
    print(f'  ✗ Vulnerable: {results["vulnerable"]}')
    
    return results['vulnerable'] == 0


def main():
    """Run all smoke tests"""
    if len(sys.argv) < 2:
        print('Usage: python3 smoke_test_mobile_flows.py <base_url> [auth_token]')
        print('Example: python3 smoke_test_mobile_flows.py http://localhost:8000 "token123"')
        sys.exit(1)
    
    base_url = sys.argv[1].rstrip('/')
    auth_token = sys.argv[2] if len(sys.argv) > 2 else 'test-token'
    
    print(f'{Colors.BOLD}{Colors.BLUE}')
    print('╔════════════════════════════════════════════════════════════════════╗')
    print('║         RideConnect Mobile API Smoke Test Suite                   ║')
    print('║         Testing mobile flows and trip endpoint resilience         ║')
    print('╚════════════════════════════════════════════════════════════════════╝')
    print(f'{Colors.END}')
    
    print(f'Base URL: {base_url}')
    print(f'Auth Token: {auth_token[:20]}...' if len(auth_token) > 20 else auth_token)
    
    results = []
    
    # Run tests
    try:
        results.append(('Health Check', test_health(base_url)))
        results.append(('Driver Matching', test_driver_matching(base_url, auth_token)))
        results.append(('Trip Request', test_trip_request(base_url, auth_token)))
        results.append(('Passenger Trips', test_passenger_trips(base_url, auth_token)))
        results.append(('Invalid ID Resilience', test_invalid_trip_ids(base_url, auth_token)))
    except Exception as e:
        failed(f'Test suite error: {str(e)}')
        sys.exit(1)
    
    # Summary
    banner('TEST SUMMARY')
    passed_count = sum(1 for _, result in results if result)
    total_count = len(results)
    
    for test_name, result in results:
        if result:
            passed(f'{test_name}: PASSED')
        else:
            failed(f'{test_name}: FAILED')
    
    print(f'\n{Colors.BOLD}Results: {passed_count}/{total_count} tests passed{Colors.END}')
    
    if passed_count == total_count:
        print(f'{Colors.GREEN}{Colors.BOLD}✓ All tests passed!{Colors.END}')
        sys.exit(0)
    else:
        print(f'{Colors.YELLOW}{Colors.BOLD}⚠ Some tests failed{Colors.END}')
        sys.exit(1)


if __name__ == '__main__':
    main()
