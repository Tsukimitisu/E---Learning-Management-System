# Curriculum System Fixes - TODO List

## Phase 1: Fix Main Curriculum Files
- [x] Fix modules/school_admin/curriculum.php - define missing variables and remove duplicates
- [ ] Complete modules/school_admin/curriculum_modals.php with all modals
- [ ] Update api/curriculum.php with proper endpoints

## Phase 2: Implement Process Files
- [ ] Complete modules/school_admin/process/add_subject.php
- [ ] Create missing process files for CRUD operations

## Phase 3: Fix JavaScript and Testing
- [ ] Update assets/js/curriculum.js with correct API calls
- [ ] Test all curriculum management functionality

## Issues Identified:
1. curriculum.php references undefined variables ($tracks, $strands, etc.)
2. Duplicate modal definitions in curriculum.php
3. Missing process files for subject management
4. Incomplete API endpoints
5. JavaScript calls non-existent endpoints
