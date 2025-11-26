# Changelog

All notable changes to this project will be documented in this file.

## [1.0.1] - 2025-11-26

### Changed
- Bumped package version to 1.0.1

## [1.1.1] - 2025-10-11

### Improved
- **Code Organization** - Refined Definition classes structure and improved namespace consistency
- **Manual Code Refinements** - Enhanced Blueprint, ForeignIdDefinition, and Grammar classes with optimizations
- **Bug Fixes** - Minor improvements to foreign key handling and method implementations

## [2.2.3] - 2025-10-11

### Added
- **Static Database Methods** - Full Laravel-compatible DB facade support for static method calls (DB::select(), DB::table(), DB::insert(), etc.)
- **Hybrid Method Support** - Manager class now supports both static and instance method calls seamlessly

### Fixed
- **Static Method Access** - Resolved "Call to undefined method DB::select()" and similar static method errors
- **Query Builder Collections** - Fixed Query\Builder get() method to return Collection objects instead of arrays for Laravel compatibility
- **Method Resolution** - Implemented proper static method delegation while maintaining backward compatibility for instance methods

### Technical Changes
- Added static method infrastructure to Capsule\Manager class
- Implemented __callStatic() method delegation to global instance
- Modified Query\Builder get() method to return Collections consistently
- Enhanced __call() method to handle table() method delegation transparently

## [2.2.2] - 2025-10-11

### Added
- **Collection Method** - Added missing `isNotEmpty()` method to Support\Collection class for checking non-empty collections

### Fixed
- **Missing Method Error** - Resolved "Call to undefined method isNotEmpty()" fatal error in Eloquent collections

## [2.2.1] - 2025-10-11

### Fixed
- **Method Signature Compatibility** - Fixed `unique()` method in Eloquent\Collection to match parent class signature and resolve PHP fatal error

## [2.2.0] - 2025-10-10

### Added
- **Complete Soft Delete System**
  - `SoftDeletes` trait for models with automatic soft delete functionality
  - `SoftDeleteScope` global scope with automatic query filtering
  - Soft delete methods: `delete()`, `forceDelete()`, `restore()`, `trashed()`
  - Query scope extensions: `withTrashed()`, `onlyTrashed()`, `withoutTrashed()`
  - Bulk operations support for soft delete queries
  - Laravel-compatible API with full feature parity

- **Enhanced Scopes System**
  - `Scope` interface for global scopes implementation
  - `ActiveScope` and `PublishedScope` common scopes
  - Global scope registration and management system
  - Local scope support with dynamic method registration
  - Scope removal and isolation mechanisms

- **Advanced Model Boot System**
  - Automatic trait discovery and boot method calling
  - Trait initialization system for model instances
  - Support for `boot{TraitName}()` and `initialize{TraitName}()` patterns
  - Recursive trait resolution with `class_uses_recursive()`

- **Model Event System**
  - Model event registration and firing mechanisms
  - Support for model lifecycle events (deleting, deleted, restoring, restored)
  - Event dispatcher integration with customizable callbacks
  - Event-driven architecture for extensible model behavior

- **Query Builder Macro System**
  - Dynamic method registration for query builder extensions
  - Macro support for custom query functionality
  - Integration with scope system for seamless API extension

### Enhanced
- **EloquentBuilder Improvements**
  - Added macro registration and dynamic method calling
  - Enhanced `onDelete` callback support for custom delete behavior
  - Improved scope integration and method forwarding
  - Better error handling for undefined methods

- **Model Class Enhancements**
  - Added global scope management methods
  - Trait boot and initialization system
  - Model event system integration
  - Enhanced helper functions for data manipulation

### Documentation
- Added comprehensive soft delete implementation guide
- Enhanced API documentation with usage examples
- Updated composer.json with new feature keywords

## [2.1.1] - 2025-10-09

### Fixed
- **BelongsTo Relationship Bug Fixes**
  - Fixed `guessBelongsToRelation()` method to properly detect relationship names from debug backtrace
  - Enhanced foreign key auto-detection to exclude internal methods
  - Resolved issue where BelongsTo relationships were using incorrect foreign key names
  - Improved relationship method name detection for automatic foreign key generation
  - Added comprehensive test suite for BelongsTo relationships validation

### Enhanced
- **Relationship Testing**
  - Added extensive BelongsTo relationship test coverage
  - Tests for eager loading, association, dissociation, and null handling
  - Validation of automatic foreign key detection functionality

## [2.1.0] - 2025-10-09

### Added
- **Complete Advanced Relationship System**
  - `hasOneThrough()` - Define has-one-through relationships with optimized SQL joins
  - `hasManyThrough()` - Define has-many-through relationships with proper table linking
  - `morphOne()` - Define polymorphic one-to-one relationships
  - `morphMany()` - Define polymorphic one-to-many relationships
  - `morphTo()` - Define polymorphic inverse relationships with type detection
  - `morphToMany()` - Define many-to-many polymorphic relationships
  - `morphedByMany()` - Define polymorphic many-to-many inverse relationships
  - `BelongsToMany` - Foundation for many-to-many relationships
  - Enhanced `Pivot` model with proper method signatures and type safety

### Enhanced
- **Model Class Improvements**
  - Added `qualifyColumn()` method for proper SQL column qualification
  - Enhanced `getMorphClass()` method for polymorphic type resolution
  - Added `morphMap` support for custom polymorphic type mapping
  - Fixed relationship instantiation methods for all relationship types
  - Improved `guessBelongsToRelation()` method with better detection logic

### Fixed
- **SQL Generation Optimization**
  - Fixed `performJoin()` methods in through relationships for correct table joins
  - Enhanced method signature compatibility across inheritance chain
  - Proper return types for all relationship methods
  - Optimized query generation with correct column qualification

### Technical Details
- All 11 relationship types fully functional and tested
- Complete Laravel Eloquent API compatibility maintained
- Enhanced test coverage with comprehensive relationship scenarios
- Production-ready performance optimizations

## [2.0.1] - 2025-10-05

### Added
- **Advanced Relationship Methods**
  - `hasOneThrough()` - Define has-one-through relationships
  - `hasManyThrough()` - Define has-many-through relationships  
  - `morphOne()` - Define polymorphic one-to-one relationships
  - `morphMany()` - Define polymorphic one-to-many relationships
  - `morphTo()` - Define polymorphic inverse relationships
  - `morphToMany()` - Define many-to-many polymorphic relationships
  - `morphedByMany()` - Define polymorphic many-to-many inverse relationships

### Enhanced
- **Model Class Improvements**
  - Fixed `__callStatic()` to properly delegate to query builder
  - Improved `guessBelongsToRelation()` method for better relationship detection
  - Added comprehensive relationship method instantiation
  - Enhanced helper functions for relationship management

### Fixed
- **Helper Functions**
  - Added `str_plural()` helper function for automatic pluralization
  - Moved helper functions to proper location in helpers.php
  - Fixed relationship method signatures and return types

### Technical Details
- Laravel-compatible relationship API
- Polymorphic relationship support foundation
- Through relationship support foundation  
- Improved code organization and documentation

## [2.0.0] - 2025-10-04

### Added
- **Enhanced Schema Builder** with 25+ advanced column types
- **Foreign Key CASCADE** support for DELETE/UPDATE operations
- **Cross-Database Compatibility** between MySQL and SQLite
- **Advanced Column Types** including JSON, UUID, enum, set, binary, longText
- **Index Management** with composite indexes and unique constraints
- **Laravel-Compatible API** with morphs(), softDeletes(), rememberToken()
- **Table Modification Methods** for altering existing table structures

### Enhanced
- **Blueprint Class** completely redesigned with advanced features
- **Grammar Classes** with intelligent SQL generation
- **Connection Management** with improved error handling
- **Query Builder** with enhanced functionality

### Fixed
- Cross-database foreign key implementation
- Schema generation for different database engines
- Column type fallbacks for unsupported features

## [1.0.0] - Initial Release

### Added
- Basic database abstraction layer
- Simple ORM functionality  
- Basic schema building
- MySQL and SQLite support
