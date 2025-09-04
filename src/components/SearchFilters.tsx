import React, { useState, useEffect, useRef } from 'react';
import { Search, Filter, X, ChevronDown, Loader2, Check, ChevronsUpDown } from 'lucide-react';
import { useDataReplacerStore } from '../store/dataReplacerStore';

interface SearchFiltersProps {
  onSearch: (filters: any) => void;
  onClear: () => void;
  isLoading: boolean;
  settings?: {
    maxResultsPerPage?: number;
  };
}

export const SearchFilters: React.FC<SearchFiltersProps> = ({ 
  onSearch, 
  onClear, 
  isLoading,
  settings
}) => {
  const [postType, setPostType] = useState('');
  const [metaKey, setMetaKey] = useState('');
  const [value, setValue] = useState('');
  const [caseSensitive, setCaseSensitive] = useState(false);
  const [useRegex, setUseRegex] = useState(false);
  const [perPage, setPerPage] = useState(settings?.maxResultsPerPage || 20);
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [isLoadingMetaKeys, setIsLoadingMetaKeys] = useState(false);
  
  // Searchable dropdown states for Post Type
  const [postTypeSearch, setPostTypeSearch] = useState('');
  const [showPostTypeDropdown, setShowPostTypeDropdown] = useState(false);
  const [selectedPostTypeIndex, setSelectedPostTypeIndex] = useState(-1);
  
  // Searchable dropdown states for Meta Key
  const [metaKeySearch, setMetaKeySearch] = useState('');
  const [showMetaKeyDropdown, setShowMetaKeyDropdown] = useState(false);
  const [selectedMetaKeyIndex, setSelectedMetaKeyIndex] = useState(-1);

  // Refs for click outside handling
  const postTypeRef = useRef<HTMLDivElement>(null);
  const metaKeyRef = useRef<HTMLDivElement>(null);

  // Get post types and meta keys from the store
  const { postTypes, metaKeys, getPostTypes, getMetaKeys } = useDataReplacerStore();

  // Load post types and meta keys when component mounts
  useEffect(() => {
    getPostTypes();
    // Also load meta keys for all post types initially
    getMetaKeys();
  }, [getPostTypes, getMetaKeys]);

  // Update perPage when settings change
  useEffect(() => {
    if (settings?.maxResultsPerPage) {
      setPerPage(settings.maxResultsPerPage);
    }
  }, [settings?.maxResultsPerPage]);

  // Load meta keys when post type changes
  useEffect(() => {
    if (postType) {
      console.log('📝 Post type changed to:', postType);
      setIsLoadingMetaKeys(true);
      getMetaKeys(postType).finally(() => {
        setIsLoadingMetaKeys(false);
      });
      // Clear meta key when post type changes
      setMetaKey('');
      setMetaKeySearch('');
      setSelectedMetaKeyIndex(-1);
    } else {
      // Clear meta keys when no post type is selected
      setMetaKey('');
      setMetaKeySearch('');
      setSelectedMetaKeyIndex(-1);
    }
  }, [postType, getMetaKeys]);

  // Click outside handlers
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (postTypeRef.current && !postTypeRef.current.contains(event.target as Node)) {
        setShowPostTypeDropdown(false);
        setSelectedPostTypeIndex(-1);
      }
      if (metaKeyRef.current && !metaKeyRef.current.contains(event.target as Node)) {
        setShowMetaKeyDropdown(false);
        setSelectedMetaKeyIndex(-1);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  // Filter post types based on search input
  const filteredPostTypes = postTypes.filter(type => 
    type.label.toLowerCase().includes(postTypeSearch.toLowerCase()) ||
    type.value.toLowerCase().includes(postTypeSearch.toLowerCase())
  );

  // Filter meta keys based on search input
  const filteredMetaKeys = metaKeys.filter(key => 
    key.toLowerCase().includes(metaKeySearch.toLowerCase())
  );

  const handlePostTypeSelect = (selectedType: any) => {
    setPostType(selectedType.value);
    setPostTypeSearch(selectedType.label);
    setShowPostTypeDropdown(false);
    setSelectedPostTypeIndex(-1);
  };

  const handleMetaKeySelect = (selectedKey: string) => {
    setMetaKey(selectedKey);
    setMetaKeySearch(selectedKey);
    setShowMetaKeyDropdown(false);
    setSelectedMetaKeyIndex(-1);
  };

  const handlePostTypeKeyDown = (e: React.KeyboardEvent) => {
    if (!showPostTypeDropdown) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedPostTypeIndex(prev => 
          prev < filteredPostTypes.length - 1 ? prev + 1 : prev
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedPostTypeIndex(prev => prev > 0 ? prev - 1 : -1);
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedPostTypeIndex >= 0 && filteredPostTypes[selectedPostTypeIndex]) {
          handlePostTypeSelect(filteredPostTypes[selectedPostTypeIndex]);
        }
        break;
      case 'Escape':
        setShowPostTypeDropdown(false);
        setSelectedPostTypeIndex(-1);
        break;
    }
  };

  const handleMetaKeyKeyDown = (e: React.KeyboardEvent) => {
    if (!showMetaKeyDropdown) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedMetaKeyIndex(prev => 
          prev < filteredMetaKeys.length - 1 ? prev + 1 : prev
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedMetaKeyIndex(prev => prev > 0 ? prev - 1 : -1);
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedMetaKeyIndex >= 0 && filteredMetaKeys[selectedMetaKeyIndex]) {
          handleMetaKeySelect(filteredMetaKeys[selectedMetaKeyIndex]);
        }
        break;
      case 'Escape':
        setShowMetaKeyDropdown(false);
        setSelectedMetaKeyIndex(-1);
        break;
    }
  };

  const handleSearch = () => {
    const filters = {
      post_type: postType,
      meta_key: metaKey,
      value: value,
      case_sensitive: caseSensitive,
      regex: useRegex,
      per_page: perPage
    };
    console.log('🔍 Searching with filters:', filters);
    onSearch(filters);
  };

  const handleClear = () => {
    setPostType('');
    setPostTypeSearch('');
    setMetaKey('');
    setMetaKeySearch('');
    setValue('');
    setCaseSensitive(false);
    setUseRegex(false);
    setPerPage(settings?.maxResultsPerPage || 20);
    setSelectedPostTypeIndex(-1);
    setSelectedMetaKeyIndex(-1);
    onClear();
  };

  const hasFilters = postType || metaKey || value || caseSensitive || useRegex;

  return (
    <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-6">
      <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between wcfdr-mb-6">
        <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
          <Filter className="wcfdr-h-5 wcfdr-w-5 wcfdr-text-gray-600" />
          <h2 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900">
            Search Filters
          </h2>
        </div>
        <button
          onClick={() => setShowAdvanced(!showAdvanced)}
          className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-1.5 wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-600 wcfdr-bg-gray-100 wcfdr-rounded-md hover:wcfdr-bg-gray-200"
        >
          Advanced
          <ChevronDown className={`wcfdr-h-4 wcfdr-w-4 wcfdr-ml-1 wcfdr-transition-transform ${
            showAdvanced ? 'wcfdr-rotate-180' : ''
          }`} />
        </button>
      </div>

      <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-4 md:wcfdr-grid-cols-2 lg:wcfdr-grid-cols-3">
        {/* Post Type - Searchable Dropdown */}
        <div ref={postTypeRef}>
          <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
            Post Type
          </label>
          <div className="wcfdr-relative">
            <div className="wcfdr-relative">
              <input
                type="text"
                value={postTypeSearch}
                onChange={(e) => {
                  setPostTypeSearch(e.target.value);
                  setShowPostTypeDropdown(true);
                  setSelectedPostTypeIndex(-1);
                }}
                onFocus={() => setShowPostTypeDropdown(true)}
                onKeyDown={handlePostTypeKeyDown}
                placeholder="Search post types..."
                className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500 wcfdr-pr-10"
              />
              <button
                type="button"
                onClick={() => setShowPostTypeDropdown(!showPostTypeDropdown)}
                className="wcfdr-absolute wcfdr-inset-y-0 wcfdr-right-0 wcfdr-flex wcfdr-items-center wcfdr-pr-3"
              >
                <ChevronsUpDown className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-gray-400" />
              </button>
            </div>

            {/* Post Type Dropdown */}
            {showPostTypeDropdown && (
              <div className="wcfdr-absolute wcfdr-z-10 wcfdr-w-full wcfdr-mt-1 wcfdr-bg-white wcfdr-border wcfdr-border-gray-300 wcfdr-rounded-md wcfdr-shadow-lg wcfdr-max-h-60 wcfdr-overflow-auto">
                {filteredPostTypes.length > 0 ? (
                  filteredPostTypes.map((type, index) => (
                    <button
                      key={type.value}
                      type="button"
                      onClick={() => handlePostTypeSelect(type)}
                      className={`wcfdr-w-full wcfdr-text-left wcfdr-px-3 wcfdr-py-2 wcfdr-text-sm wcfdr-text-gray-700 hover:wcfdr-bg-gray-100 wcfdr-flex wcfdr-items-center wcfdr-justify-between ${
                        index === selectedPostTypeIndex ? 'wcfdr-bg-blue-50 wcfdr-text-blue-900' : ''
                      }`}
                    >
                      <div>
                        <div className="wcfdr-font-medium">{type.label}</div>
                        <div className="wcfdr-text-xs wcfdr-text-gray-500">{type.value} ({type.count})</div>
                      </div>
                      {postType === type.value && <Check className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-blue-600" />}
                    </button>
                  ))
                ) : (
                  <div className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-sm wcfdr-text-gray-500">
                    No post types found
                  </div>
                )}
              </div>
            )}
          </div>
          
          <p className="wcfdr-mt-1 wcfdr-text-xs wcfdr-text-gray-500">
            {postTypes.length} post type{postTypes.length !== 1 ? 's' : ''} available
          </p>
        </div>

        {/* Meta Key - Searchable Dropdown */}
        <div ref={metaKeyRef}>
          <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
            Meta Key
          </label>
          <div className="wcfdr-relative">
            <div className="wcfdr-relative">
              <input
                type="text"
                value={metaKeySearch}
                onChange={(e) => {
                  setMetaKeySearch(e.target.value);
                  setShowMetaKeyDropdown(true);
                  setSelectedMetaKeyIndex(-1);
                }}
                onFocus={() => setShowMetaKeyDropdown(true)}
                onKeyDown={handleMetaKeyKeyDown}
                placeholder={!postType ? 'Select Post Type First' : isLoadingMetaKeys ? 'Loading...' : 'Search meta keys...'}
                disabled={!postType || isLoadingMetaKeys}
                className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed wcfdr-pr-10"
              />
              <button
                type="button"
                onClick={() => setShowMetaKeyDropdown(!showMetaKeyDropdown)}
                disabled={!postType || isLoadingMetaKeys}
                className="wcfdr-absolute wcfdr-inset-y-0 wcfdr-right-0 wcfdr-flex wcfdr-items-center wcfdr-pr-3"
              >
                {isLoadingMetaKeys ? (
                  <Loader2 className="wcfdr-h-4 wcfdr-w-4 wcfdr-animate-spin wcfdr-text-gray-400" />
                ) : (
                  <ChevronsUpDown className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-gray-400" />
                )}
              </button>
            </div>

            {/* Meta Key Dropdown */}
            {showMetaKeyDropdown && postType && !isLoadingMetaKeys && (
              <div className="wcfdr-absolute wcfdr-z-10 wcfdr-w-full wcfdr-mt-1 wcfdr-bg-white wcfdr-border wcfdr-border-gray-300 wcfdr-rounded-md wcfdr-shadow-lg wcfdr-max-h-60 wcfdr-overflow-auto">
                {filteredMetaKeys.length > 0 ? (
                  filteredMetaKeys.map((key, index) => (
                    <button
                      key={key}
                      type="button"
                      onClick={() => handleMetaKeySelect(key)}
                      className={`wcfdr-w-full wcfdr-text-left wcfdr-px-3 wcfdr-py-2 wcfdr-text-sm wcfdr-text-gray-700 hover:wcfdr-bg-gray-100 wcfdr-flex wcfdr-items-center wcfdr-justify-between ${
                        index === selectedMetaKeyIndex ? 'wcfdr-bg-blue-50 wcfdr-text-blue-900' : ''
                      }`}
                    >
                      <span className="wcfdr-truncate">{key}</span>
                      {metaKey === key && <Check className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-blue-600" />}
                    </button>
                  ))
                ) : (
                  <div className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-sm wcfdr-text-gray-500">
                    No meta keys found
                  </div>
                )}
              </div>
            )}
          </div>
          
          <p className="wcfdr-mt-1 wcfdr-text-xs wcfdr-text-gray-500">
            {!postType 
              ? 'Select a post type to see available meta keys'
              : isLoadingMetaKeys 
                ? 'Loading meta keys...' 
                : `${metaKeys.length} meta key${metaKeys.length !== 1 ? 's' : ''} found for ${postType}`
            }
          </p>
        </div>

        {/* Value Contains */}
        <div>
          <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
            Value Contains
          </label>
          <input
            type="text"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            placeholder="Search within meta values..."
            className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
          />
        </div>
      </div>

      {/* Advanced Options */}
      {showAdvanced && (
        <div className="wcfdr-mt-6 wcfdr-pt-6 wcfdr-border-t wcfdr-border-gray-200">
          <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-4 md:wcfdr-grid-cols-3">
            {/* Case Sensitive */}
            <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
              <label className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700">
                Case Sensitive
              </label>
              <input
                type="checkbox"
                checked={caseSensitive}
                onChange={(e) => setCaseSensitive(e.target.checked)}
                className="wcfdr-rounded wcfdr-border-gray-300 wcfdr-text-blue-600 focus:wcfdr-ring-blue-500"
              />
            </div>

            {/* Use Regex */}
            <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
              <label className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700">
                Use Regular Expression
              </label>
              <input
                type="checkbox"
                checked={useRegex}
                onChange={(e) => setUseRegex(e.target.checked)}
                className="wcfdr-rounded wcfdr-border-gray-300 wcfdr-text-blue-600 focus:wcfdr-ring-blue-500"
              />
            </div>

            {/* Results Per Page */}
            <div>
              <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
                Results Per Page
              </label>
              <select
                value={perPage}
                onChange={(e) => setPerPage(Number(e.target.value))}
                className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
              >
                <option value={10}>10</option>
                <option value={20}>20</option>
                <option value={50}>50</option>
                <option value={100}>100</option>
                <option value={200}>200</option>
                <option value={500}>500</option>
                <option value={1000}>1,000</option>
                <option value={1500}>1,500</option>
              </select>
            </div>
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="wcfdr-mt-6 wcfdr-flex wcfdr-items-center wcfdr-justify-between">
        <div className="wcfdr-flex wcfdr-space-x-3">
          <button
            onClick={handleSearch}
            disabled={isLoading || (!metaKey && !value)}
            className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-transparent wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-blue-600 hover:wcfdr-bg-blue-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
          >
            <Search className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
            {isLoading ? 'Searching...' : 'Search'}
          </button>
          
          <button
            onClick={handleClear}
            disabled={!hasFilters}
            className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
          >
            <X className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
            Clear
          </button>
        </div>

        {/* Results Count */}
        <div className="wcfdr-text-sm wcfdr-text-gray-500">
          {hasFilters && (
            <span className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2.5 wcfdr-py-0.5 wcfdr-rounded-full wcfdr-text-xs wcfdr-font-medium wcfdr-bg-blue-100 wcfdr-text-blue-800">
              Filters Active
            </span>
          )}
        </div>
      </div>
    </div>
  );
};
