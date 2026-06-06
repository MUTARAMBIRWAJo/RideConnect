# Passenger Profile Fields Migration - SQL Commands

Execute these SQL commands directly in your Supabase database to add the missing columns to the `users` table.

## SQL Migration

```sql
-- Add passenger profile fields to users table
ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(50) DEFAULT 'card',
ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20);

-- Verify columns were added
SELECT column_name, data_type, is_nullable, column_default 
FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name IN ('preferred_payment_method', 'emergency_contact_name', 'emergency_contact_phone')
ORDER BY ordinal_position;
```

## How to Execute

### Option 1: Using Supabase Dashboard
1. Go to https://app.supabase.com/
2. Select your RideConnect project
3. Go to **SQL Editor** (left sidebar)
4. Create a new query or paste into the editor
5. Copy and paste the SQL commands above
6. Click **Run** button

### Option 2: Using psql command line
```bash
psql -h aws-1-us-east-1.pooler.supabase.com -U postgres.tpahuvmhlfluztuhznfj -d postgres -c "
ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(50) DEFAULT 'card',
ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20);
"
```

## Verification

After running the migration, verify the columns exist:

```sql
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name IN ('preferred_payment_method', 'emergency_contact_name', 'emergency_contact_phone');
```

Expected output should show 3 new columns.

## After Migration

Once the columns are added to the database, the `PUT /api/v1/passenger/profile` endpoint will work correctly to update passenger profiles.

## Test the Endpoint

```bash
curl -X PUT http://your-api.com/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Mugabo",
    "phone": "+250780126094",
    "preferred_payment_method": "mobile_money",
    "emergency_contact_name": "Marie Mugabo",
    "emergency_contact_phone": "+250788654321"
  }'
```
