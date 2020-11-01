ALTER TABLE items ADD COLUMN submitterid int(11) NOT NULL DEFAULT '0' AFTER userid;
UPDATE items SET submitterid = userid;
