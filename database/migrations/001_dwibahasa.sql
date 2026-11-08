-- ======================================================================
-- Migrasi 001: Penambahan Kolom Dwibahasa (ID/EN)
-- Menambahkan kolom deskripsi_en dan fasilitas_en pada tipe_villa,
-- serta kolom preferensi bahasa pada tabel pengguna.
-- ======================================================================

-- Kolom deskripsi_en dan fasilitas_en pada tipe_villa (jika belum ada)
-- ALTER TABLE tipe_villa ADD COLUMN deskripsi_en TEXT NULL AFTER deskripsi;
-- ALTER TABLE tipe_villa ADD COLUMN fasilitas_en TEXT NULL AFTER fasilitas;

ALTER TABLE pengguna
  ADD COLUMN bahasa VARCHAR(5) NOT NULL DEFAULT 'id' AFTER kontak;

-- Update data bahasa Inggris untuk 3 tipe villa
UPDATE tipe_villa
SET 
  deskripsi_en = 'Exclusive Joglo with exotic black wood, private pool, and tranquil tropical garden view. Perfect for couples or small families.',
  fasilitas_en = '["King Bed", "Private Pool", "WiFi 50 Mbps", "AC & Smart TV", "Luxury Bathtub", "Kitchenette"]'
WHERE slug = 'joglo-wulung';

UPDATE tipe_villa
SET 
  deskripsi_en = 'Traditional teak Joglo with open living room concept and direct garden view. Features 2 spacious master bedrooms.',
  fasilitas_en = '["2 King Beds", "Private Pool", "WiFi 50 Mbps", "AC in All Rooms", "Spacious Living Room", "Dining Room", "Kitchen"]'
WHERE slug = 'joglo-saren';

UPDATE tipe_villa
SET 
  deskripsi_en = 'Grand royal Joglo with authentic high tumpangsari ceiling, private swimming pool, and pavilion for large family gatherings.',
  fasilitas_en = '["3 King Beds + 1 Twin", "Private Swimming Pool", "WiFi 100 Mbps", "Full AC", "Grand Living Hall", "Private Kitchen", "BBQ Area"]'
WHERE slug = 'joglo-agung';
