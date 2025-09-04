import React, { useState, useCallback } from 'react'
import { SearchFilters } from './SearchFilters'
import { ResultsTable } from './ResultsTable'
import { LiveTester } from './LiveTester'
import { BulkActions } from './BulkActions'
import { Header } from './Header'
import { Sidebar } from './Sidebar'
import { useSearchStore } from '../stores/searchStore'
import { useReplaceStore } from '../stores/replaceStore'
import { useToast } from '../hooks/useToast'
import { SearchResult, ReplaceMode } from '../types'

export const DataReplacerApp: React.FC = () => {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const [selectedRows, setSelectedRows] = useState<SearchResult[]>([])
  const [isLiveTesterOpen, setIsLiveTesterOpen] = useState(false)
  
  const { searchResults, isLoading, searchError } = useSearchStore()
  const { replaceMode, findValue, replaceValue } = useReplaceStore()
  const { toast } = useToast()

  // Handle row selection
  const handleRowSelect = useCallback((row: SearchResult, isSelected: boolean) => {
    if (isSelected) {
      setSelectedRows(prev => [...prev, row])
    } else {
      setSelectedRows(prev => prev.filter(r => r.post_id !== row.post_id))
    }
  }, [])

  // Handle bulk selection
  const handleSelectAll = useCallback((isSelected: boolean) => {
    if (isSelected) {
      setSelectedRows(searchResults.rows || [])
    } else {
      setSelectedRows([])
    }
  }, [searchResults.rows])

  // Handle search completion
  const handleSearchComplete = useCallback((results: any) => {
    if (results.total > 0) {
      toast({
        title: 'Search Complete',
        description: `Found ${results.total} results in ${results.total_pages} pages`,
        type: 'success'
      })
    } else {
      toast({
        title: 'No Results',
        description: 'No posts found matching your criteria',
        type: 'info'
      })
    }
  }, [toast])

  // Handle search error
  const handleSearchError = useCallback((error: string) => {
    toast({
      title: 'Search Error',
      description: error,
      type: 'error'
    })
  }, [toast])

  // Handle replace completion
  const handleReplaceComplete = useCallback((results: any) => {
    if (results.updated > 0) {
      toast({
        title: 'Replace Complete',
        description: `Successfully updated ${results.updated} posts`,
        type: 'success'
      })
      // Refresh search results
      // This would typically trigger a refetch
    }
  }, [toast])

  // Handle replace error
  const handleReplaceError = useCallback((error: string) => {
    toast({
      title: 'Replace Error',
      description: error,
      type: 'error'
    })
  }, [toast])

  return (
    <div className="wcfdr-min-h-screen wcfdr-bg-background wcfdr-flex wcfdr-flex-col">
      {/* Header */}
      <Header 
        onMenuClick={() => setIsSidebarOpen(true)}
        onLiveTesterClick={() => setIsLiveTesterOpen(true)}
        selectedCount={selectedRows.length}
        totalResults={searchResults.total || 0}
      />

      {/* Main Content */}
      <div className="wcfdr-flex wcfdr-flex-1 wcfdr-overflow-hidden">
        {/* Sidebar */}
        <Sidebar 
          isOpen={isSidebarOpen}
          onClose={() => setIsSidebarOpen(false)}
        />

        {/* Main Content Area */}
        <main className="wcfdr-flex-1 wcfdr-overflow-auto wcfdr-p-6">
          <div className="wcfdr-max-w-7xl wcfdr-mx-auto wcfdr-space-y-6">
            
            {/* Search Filters */}
            <SearchFilters 
              onSearchComplete={handleSearchComplete}
              onSearchError={handleSearchError}
            />

            {/* Results Section */}
            {searchResults.total !== undefined && (
              <div className="wcfdr-space-y-4">
                {/* Results Header */}
                <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
                  <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-4">
                    <h2 className="wcfdr-text-xl wcfdr-font-semibold">
                      Search Results
                    </h2>
                    <span className="wcfdr-text-sm wcfdr-text-muted-foreground">
                      {searchResults.total} posts found
                    </span>
                    {isLoading && (
                      <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
                        <div className="wcfdr-w-4 wcfdr-h-4 wcfdr-border-2 wcfdr-border-primary wcfdr-border-t-transparent wcfdr-rounded-full wcfdr-animate-spin"></div>
                        <span className="wcfdr-text-sm wcfdr-text-muted-foreground">Searching...</span>
                      </div>
                    )}
                  </div>
                  
                  {/* Quick Actions */}
                  <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
                    {selectedRows.length > 0 && (
                      <span className="wcfdr-text-sm wcfdr-text-muted-foreground">
                        {selectedRows.length} selected
                      </span>
                    )}
                    {searchResults.total > 0 && (
                      <button
                        onClick={() => setIsLiveTesterOpen(true)}
                        className="wcfdr-bg-secondary wcfdr-text-secondary-foreground wcfdr-px-3 wcfdr-py-2 wcfdr-rounded-md wcfdr-text-sm wcfdr-font-medium hover:wcfdr-bg-secondary/80 wcfdr-transition-colors"
                      >
                        Live Tester
                      </button>
                    )}
                  </div>
                </div>

                {/* Results Table */}
                {searchResults.rows && searchResults.rows.length > 0 ? (
                  <ResultsTable 
                    results={searchResults.rows}
                    selectedRows={selectedRows}
                    onRowSelect={handleRowSelect}
                    onSelectAll={handleSelectAll}
                    onReplaceComplete={handleReplaceComplete}
                    onReplaceError={handleReplaceError}
                  />
                ) : searchResults.total === 0 ? (
                  <div className="wcfdr-text-center wcfdr-py-12">
                    <div className="wcfdr-w-16 wcfdr-h-16 wcfdr-mx-auto wcfdr-mb-4 wcfdr-text-muted-foreground">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-mb-2">No results found</h3>
                    <p className="wcfdr-text-muted-foreground wcfdr-mb-4">
                      Try adjusting your search criteria or check the spelling of your search terms.
                    </p>
                  </div>
                ) : null}

                {/* Pagination */}
                {searchResults.total_pages && searchResults.total_pages > 1 && (
                  <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between wcfdr-border-t wcfdr-pt-4">
                    <div className="wcfdr-text-sm wcfdr-text-muted-foreground">
                      Page {searchResults.page} of {searchResults.total_pages}
                    </div>
                    <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
                      <button
                        disabled={!searchResults.has_prev_page}
                        className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-sm wcfdr-font-medium wcfdr-text-muted-foreground wcfdr-disabled:opacity-50 wcfdr-disabled:cursor-not-allowed hover:wcfdr-text-foreground wcfdr-transition-colors"
                      >
                        Previous
                      </button>
                      <button
                        disabled={!searchResults.has_next_page}
                        className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-sm wcfdr-font-medium wcfdr-text-muted-foreground wcfdr-disabled:opacity-50 wcfdr-disabled:cursor-not-allowed hover:wcfdr-text-foreground wcfdr-transition-colors"
                      >
                        Next
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Bulk Actions */}
            {selectedRows.length > 0 && (
              <BulkActions 
                selectedRows={selectedRows}
                onReplaceComplete={handleReplaceComplete}
                onReplaceError={handleReplaceError}
                onClearSelection={() => setSelectedRows([])}
              />
            )}
          </div>
        </main>
      </div>

      {/* Live Tester Panel */}
      <LiveTester 
        isOpen={isLiveTesterOpen}
        onClose={() => setIsLiveTesterOpen(false)}
        sampleData={searchResults.rows?.[0]?.meta_value}
        onReplaceComplete={handleReplaceComplete}
        onReplaceError={handleReplaceError}
      />

      {/* Error Display */}
      {searchError && (
        <div className="wcfdr-fixed wcfdr-bottom-4 wcfdr-right-4 wcfdr-z-50">
          <div className="wcfdr-bg-destructive wcfdr-text-destructive-foreground wcfdr-p-4 wcfdr-rounded-lg wcfdr-shadow-lg wcfdr-max-w-md">
            <div className="wcfdr-flex wcfdr-items-start wcfdr-space-x-3">
              <svg className="wcfdr-w-5 wcfdr-h-5 wcfdr-mt-0.5 wcfdr-flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              <div className="wcfdr-flex-1">
                <h4 className="wcfdr-font-medium">Search Error</h4>
                <p className="wcfdr-text-sm wcfdr-mt-1">{searchError}</p>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
