-- Migration: Add notes column to grades table
-- Run this migration if the notes column doesn't exist

-- Check and add notes column to grades table
ALTER TABLE grades ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER remarks;

-- Add index for faster searches on notes (optional)
-- ALTER TABLE grades ADD FULLTEXT INDEX idx_notes (notes);
