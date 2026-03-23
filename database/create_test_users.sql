-- Fast SQL-only user creation for test accounts
INSERT INTO users (email, name, password, role, is_verified, created_at, updated_at) 
VALUES 
  ('john.kamanzi@rideconnect.rw', 'John Kamanzi', '$2y$12$vBW89C9ZkLR/QIdHIzlOt.XBJx5WCnPFhXLkYuS.NXrvMVL6R6p2O', 'ADMIN', true, NOW(), NOW()),
  ('yvonne.mutoni@rideconnect.rw', 'Yvonne Mutoni', '$2y$12$vBW89C9ZkLR/QIdHIzlOt.XBJx5WCnPFhXLkYuS.NXrvMVL6R6p2O', 'ACCOUNTANT', true, NOW(), NOW()),
  ('sarah.uwase@rideconnect.rw', 'Sarah Uwase', '$2y$12$vBW89C9ZkLR/QIdHIzlOt.XBJx5WCnPFhXLkYuS.NXrvMVL6R6p2O', 'OFFICER', true, NOW(), NOW())
ON CONFLICT (email) DO UPDATE SET password = EXCLUDED.password, role = EXCLUDED.role, updated_at = NOW();

-- Sync roles to model_has_roles
INSERT INTO model_has_roles (role_id, model_id, model_type) 
SELECT r.id, u.id, 'App\Models\User'
FROM users u
JOIN roles r ON (u.role = 'ADMIN' AND r.name = 'Admin') OR (u.role = 'ACCOUNTANT' AND r.name = 'Accountant') OR (u.role = 'OFFICER' AND r.name = 'Officer')
WHERE u.email IN ('john.kamanzi@rideconnect.rw', 'yvonne.mutoni@rideconnect.rw', 'sarah.uwase@rideconnect.rw')
ON CONFLICT (role_id, model_id, model_type) DO NOTHING;
