import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import './styles/globals.css';

// Import components
import { Header } from './components/Header';
import { SearchFilters } from './components/SearchFilters';
import { ResultsTable } from './components/ResultsTable';
import { LiveTester } from './components/LiveTester';
import { BulkActions } from './components/BulkActions';
import { Sidebar } from './components/Sidebar';
import { LoadingSpinner } from './components/ui/LoadingSpinner';
import { useDataReplacerStore } from './store/dataReplacerStore';
import { Loader2 } from 'lucide-react';

// Declare WordPress AJAX object
declare global {
  interface Window {
    wcfdr_ajax: {
      ajax_url: string;
      nonce: string;
    };
  }
}

// Main admin app component
const DataReplacerApp: React.FC = () => {
  const [isLoading, setIsLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'search' | 'live-tester' | 'backups' | 'settings' | 'help'>('search');
  
  // Store current search filters to preserve them
  const [currentFilters, setCurrentFilters] = useState<any>(null);
  
  // Settings state
  const [settings, setSettings] = useState({
    maxResultsPerPage: 1500,
    maxBulkOperations: 5000,
    backupRetention: 10,
    autoCleanup: 30
  });
  const [isSavingSettings, setIsSavingSettings] = useState(false);
  const [settingsMessage, setSettingsMessage] = useState<{type: 'success' | 'error', text: string} | null>(null);
  
  const { 
    searchResults, 
    isLoading: isSearching, 
    error,
    initializeStore,
    searchMeta
  } = useDataReplacerStore();

  // Function to load settings from WordPress or localStorage
  const loadSettings = async () => {
    try {
      // Try to load from WordPress first
      if (window.wcfdr_ajax) {
        const formData = new FormData();
        formData.append('action', 'wcfdr_get_settings');
        formData.append('nonce', window.wcfdr_ajax.nonce);
        
        const response = await fetch(window.wcfdr_ajax.ajax_url, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });
        
        if (response.ok) {
          const result = await response.json();
          if (result.success && result.data) {
            setSettings(result.data);
            return;
          }
        }
      }
      
      // Fallback to localStorage
      const savedSettings = localStorage.getItem('wcfdr_settings');
      if (savedSettings) {
        try {
          const parsedSettings = JSON.parse(savedSettings);
          setSettings(parsedSettings);
        } catch (e) {
          console.warn('Failed to parse saved settings from localStorage');
        }
      }
    } catch (error) {
      console.warn('Failed to load settings from WordPress, using defaults');
    }
  };

  useEffect(() => {
    // Initialize the store and test connection
    const init = async () => {
      try {
        console.log('🚀 WCF Data Replacer React App Initializing...');
        await initializeStore();
        await loadSettings(); // Load settings after store initialization
        setIsLoading(false);
        console.log('✅ WCF Data Replacer React App Ready!');
      } catch (err) {
        console.error('❌ Failed to initialize store:', err);
        setIsLoading(false);
      }
    };
    
    init();
  }, [initializeStore]);

  // Function to handle settings button clicks
  const handleSettingsClick = () => {
    setActiveTab('settings');
  };

  // Function to handle help button clicks
  const handleHelpClick = () => {
    setActiveTab('help');
  };

  // Function to handle search with filter preservation
  const handleSearch = async (filters: any) => {
    try {
      console.log('🔍 Searching with filters:', filters);
      setCurrentFilters(filters); // Store current filters
      await searchMeta(filters);
    } catch (error) {
      console.error('❌ Search failed:', error);
    }
  };

  // Function to refresh search results with current filters
  const refreshResults = async () => {
    if (currentFilters) {
      try {
        console.log('🔄 Refreshing results with filters:', currentFilters);
        await searchMeta(currentFilters);
      } catch (error) {
        console.error('❌ Failed to refresh results:', error);
      }
    }
  };

  // Function to save settings
  const handleSaveSettings = async () => {
    setIsSavingSettings(true);
    setSettingsMessage(null);
    
    try {
      // Create form data for AJAX request
      const formData = new FormData();
      formData.append('action', 'wcfdr_save_settings');
      formData.append('nonce', window.wcfdr_ajax.nonce);
      formData.append('settings', JSON.stringify(settings));
      
      // Send AJAX request to WordPress
      const response = await fetch(window.wcfdr_ajax.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        setSettingsMessage({
          type: 'success',
          text: 'Settings saved successfully!'
        });
        
        // Store settings in localStorage as backup
        localStorage.setItem('wcfdr_settings', JSON.stringify(settings));
        
        // Clear message after 3 seconds
        setTimeout(() => setSettingsMessage(null), 3000);
      } else {
        throw new Error(result.data?.message || 'Failed to save settings');
      }
      
    } catch (error) {
      console.error('❌ Settings save failed:', error);
      setSettingsMessage({
        type: 'error',
        text: `Failed to save settings: ${error instanceof Error ? error.message : 'Unknown error'}`
      });
    } finally {
      setIsSavingSettings(false);
    }
  };

  if (isLoading) {
    return <LoadingSpinner size="large" />;
  }

  return (
    <div className="wcfdr-min-h-screen wcfdr-bg-gray-50">
      <Header onSettingsClick={handleSettingsClick} onHelpClick={handleHelpClick} />
      
      <div className="wcfdr-flex wcfdr-min-h-screen">
        <Sidebar activeTab={activeTab} onTabChange={setActiveTab} onSettingsClick={handleSettingsClick} />
        
        <main className="wcfdr-flex-1 wcfdr-p-6">
          {activeTab === 'search' && (
            <div className="wcfdr-space-y-6">
              <SearchFilters 
                onSearch={handleSearch}
                onClear={() => {
                  console.log('🧹 Clearing search');
                  setCurrentFilters(null);
                  // Clear search results
                  useDataReplacerStore.getState().searchResults = null;
                }}
                isLoading={isSearching}
                settings={settings}
              />
              <ResultsTable 
                results={searchResults}
                onUpdateRow={async (data) => {
                  try {
                    console.log('Update row:', data);
                    await useDataReplacerStore.getState().updateRow(data);
                    // Refresh search results to show updated data
                    await refreshResults();
                  } catch (error) {
                    console.error('Failed to update row:', error);
                  }
                }}
                onRestoreRow={async (data) => {
                  try {
                    console.log('Restore row:', data);
                    await useDataReplacerStore.getState().restoreRow(data.post_id, data.meta_key);
                    // Refresh search results to show restored data
                    await refreshResults();
                  } catch (error) {
                    console.error('Failed to restore row:', error);
                  }
                }}
                onPageChange={async (page: number) => {
                  if (currentFilters) {
                    try {
                      console.log('📄 Changing to page:', page);
                      const pageFilters = { ...currentFilters, page };
                      await searchMeta(pageFilters);
                    } catch (error) {
                      console.error('Failed to change page:', error);
                    }
                  }
                }}
              />
            </div>
          )}
          
          {activeTab === 'live-tester' && (
            <LiveTester />
          )}
          
          {activeTab === 'backups' && (
            <div className="wcfdr-space-y-6">
              <h2 className="wcfdr-text-2xl wcfdr-font-bold wcfdr-text-gray-900">
                Backup Management
              </h2>
              <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-12 wcfdr-text-center">
                <div className="wcfdr-text-gray-400 wcfdr-mb-4">
                  <svg className="wcfdr-h-12 wcfdr-w-12 wcfdr-mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                  </svg>
                </div>
                <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-2">
                  Backup Management
                </h3>
                <p className="wcfdr-text-gray-500">
                  Manage your post meta backups and restore operations.
                </p>
                <p className="wcfdr-text-sm wcfdr-text-gray-400 wcfdr-mt-2">
                  Coming soon in the next update.
                </p>
              </div>
            </div>
          )}

          {activeTab === 'settings' && (
            <div className="wcfdr-space-y-6">
              <h2 className="wcfdr-text-2xl wcfdr-font-bold wcfdr-text-gray-900">
                Plugin Settings
              </h2>
              
              {/* Settings Message */}
              {settingsMessage && (
                <div className={`wcfdr-p-4 wcfdr-rounded-lg wcfdr-border ${
                  settingsMessage.type === 'success' 
                    ? 'wcfdr-bg-green-50 wcfdr-border-green-200 wcfdr-text-green-800' 
                    : 'wcfdr-bg-red-50 wcfdr-border-red-200 wcfdr-text-red-800'
                }`}>
                  {settingsMessage.text}
                </div>
              )}
              
              <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-6">
                <div className="wcfdr-space-y-6">
                  {/* General Settings */}
                  <div>
                    <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-4">
                      General Settings
                    </h3>
                    <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-4 md:wcfdr-grid-cols-2">
                      <div>
                        <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
                          Max Results Per Page
                        </label>
                        <select 
                          value={settings.maxResultsPerPage}
                          onChange={(e) => setSettings(prev => ({ ...prev, maxResultsPerPage: Number(e.target.value) }))}
                          className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                        >
                          <option value={20}>20</option>
                          <option value={50}>50</option>
                          <option value={100}>100</option>
                          <option value={200}>200</option>
                          <option value={500}>500</option>
                          <option value={1000}>1,000</option>
                          <option value={1500}>1,500</option>
                        </select>
                      </div>
                      <div>
                        <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
                          Max Bulk Operations
                        </label>
                        <select 
                          value={settings.maxBulkOperations}
                          onChange={(e) => setSettings(prev => ({ ...prev, maxBulkOperations: Number(e.target.value) }))}
                          className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                        >
                          <option value={1000}>1,000</option>
                          <option value={5000}>5,000</option>
                          <option value={10000}>10,000</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  {/* Backup Settings */}
                  <div>
                    <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-4">
                      Backup Settings
                    </h3>
                    <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-4 md:wcfdr-grid-cols-2">
                      <div>
                        <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
                          Backup Retention (revisions)
                        </label>
                        <select 
                          value={settings.backupRetention}
                          onChange={(e) => setSettings(prev => ({ ...prev, backupRetention: Number(e.target.value) }))}
                          className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                        >
                          <option value={5}>5</option>
                          <option value={10}>10</option>
                          <option value={20}>20</option>
                          <option value={50}>50</option>
                        </select>
                      </div>
                      <div>
                        <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
                          Auto Cleanup (days)
                        </label>
                        <select 
                          value={settings.autoCleanup}
                          onChange={(e) => setSettings(prev => ({ ...prev, autoCleanup: Number(e.target.value) }))}
                          className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                        >
                          <option value={30}>30</option>
                          <option value={60}>60</option>
                          <option value={90}>90</option>
                          <option value={180}>180</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  {/* Save Button */}
                  <div className="wcfdr-pt-4 wcfdr-border-t wcfdr-border-gray-200">
                    <button 
                      onClick={handleSaveSettings}
                      disabled={isSavingSettings}
                      className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-transparent wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-blue-600 hover:wcfdr-bg-blue-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
                    >
                      {isSavingSettings ? (
                        <>
                          <Loader2 className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2 wcfdr-animate-spin" />
                          Saving...
                        </>
                      ) : (
                        'Save Settings'
                      )}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'help' && (
            <div className="wcfdr-space-y-6">
              <h2 className="wcfdr-text-2xl wcfdr-font-bold wcfdr-text-gray-900">
                Help & Documentation
              </h2>
              <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-6">
                <div className="wcfdr-space-y-6">
                  {/* Quick Start */}
                  <div>
                    <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-4">
                      Quick Start Guide
                    </h3>
                    <div className="wcfdr-prose wcfdr-max-w-none">
                      <ol className="wcfdr-list-decimal wcfdr-list-inside wcfdr-space-y-2 wcfdr-text-gray-700">
                        <li>Select a Post Type from the dropdown (e.g., Posts, Pages, or your custom post type)</li>
                        <li>Choose a Meta Key to search within (e.g., _thumbnail_id, custom_field)</li>
                        <li>Optionally enter a value to search for within the meta values</li>
                        <li>Click Search to find matching posts</li>
                        <li>Use the Live Tester to preview replacements before applying them</li>
                        <li>Apply changes individually or use Bulk Actions for multiple updates</li>
                      </ol>
                    </div>
                  </div>

                  {/* Search Modes */}
                  <div>
                    <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-4">
                      Search Modes
                    </h3>
                    <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-4 md:wcfdr-grid-cols-2">
                      <div className="wcfdr-bg-gray-50 wcfdr-p-4 wcfdr-rounded-lg">
                        <h4 className="wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-2">Plain Text</h4>
                        <p className="wcfdr-text-sm wcfdr-text-gray-600">Simple text search and replace (case-insensitive by default)</p>
                      </div>
                      <div className="wcfdr-bg-gray-50 wcfdr-p-4 wcfdr-rounded-lg">
                        <h4 className="wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-2">Regular Expression</h4>
                        <p className="wcfdr-text-sm wcfdr-text-gray-600">Advanced pattern matching using PCRE syntax</p>
                      </div>
                      <div className="wcfdr-bg-gray-50 wcfdr-p-4 wcfdr-rounded-lg">
                        <h4 className="wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-2">URL Operations</h4>
                        <p className="wcfdr-text-sm wcfdr-text-gray-600">Specialized URL manipulation (domain swap, path changes)</p>
                      </div>
                      <div className="wcfdr-bg-gray-50 wcfdr-p-4 wcfdr-rounded-lg">
                        <h4 className="wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-2">Full Text Overwrite</h4>
                        <p className="wcfdr-text-sm wcfdr-text-gray-600">Replace entire meta value with new content</p>
                      </div>
                    </div>
                  </div>

                  {/* Safety Features */}
                  <div>
                    <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-4">
                      Safety Features
                    </h3>
                    <div className="wcfdr-bg-blue-50 wcfdr-border wcfdr-border-blue-200 wcfdr-rounded-lg wcfdr-p-4">
                      <ul className="wcfdr-list-disc wcfdr-list-inside wcfdr-space-y-1 wcfdr-text-blue-800">
                        <li><strong>Automatic Backups:</strong> Every change creates a backup before modification</li>
                        <li><strong>Live Preview:</strong> Test replacements before applying them</li>
                        <li><strong>Dry Run Mode:</strong> See what would change without making actual changes</li>
                        <li><strong>Individual Restore:</strong> Restore any single change from backup</li>
                        <li><strong>Bulk Restore:</strong> Restore all changes in a search result</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  );
};

// Initialize the app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('🎯 DOM Ready - Initializing WCF Data Replacer...');
  const container = document.getElementById('wcfdr-admin-app');
  if (container) {
    console.log('✅ Found container, creating React root...');
    const root = ReactDOM.createRoot(container);
    root.render(<DataReplacerApp />);
    console.log('🎉 React app rendered successfully!');
  } else {
    console.error('❌ Container #wcfdr-admin-app not found!');
  }
});
