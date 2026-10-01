-- Preserve existing video records. Old local-file paths remain editable in the admin,
-- but only valid Bunny Stream embed URLs appear on the public site.
ALTER TABLE videos CHANGE COLUMN video_path video_url VARCHAR(2048) NOT NULL;
