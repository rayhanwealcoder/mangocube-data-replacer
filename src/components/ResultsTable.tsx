import React, { useState } from 'react';
import { Edit, RotateCcw, Eye, AlertTriangle, CheckCircle, ChevronLeft, ChevronRight, CheckSquare, Square, Zap, Play, X } from 'lucide-react';

interface SearchResult {
  total: number;
  total_pages: number;
  page: number;
  per_page: number;
  rows: Array<{
    post_id: number;
    post_title: string;
    meta_key: string;
    meta_value: string;
    has_backup: boolean;
    is_modified?: boolean;
  }>;
}

interface ResultsTableProps {
  results: SearchResult | null;
  onUpdateRow: (data: any) => void;
  onRestoreRow: (data: any) => void;
  onPageChange?: (page: number) => void;
}

export const ResultsTable: React.FC<ResultsTableProps> = ({ 
  results, 
  onUpdateRow, 
  onRestoreRow,
  onPageChange
}) => {
  const [editingRow, setEditingRow] = useState<number | null>(null);
  const [editValue, setEditValue] = useState('');
  const [isUpdating, setIsUpdating] = useState(false);
  const [updateMessage, setUpdateMessage] = useState<{type: 'success' | 'error', text: string} | null>(null);
  
  // Bulk actions state
  const [selectedRows, setSelectedRows] = useState<Set<string>>(new Set());
  const [showBulkActions, setShowBulkActions] = useState(false);
  const [bulkMode, setBulkMode] = useState('plain');
  const [findText, setFindText] = useState('');
  const [replaceText, setReplaceText] = useState('');
  const [caseSensitive, setCaseSensitive] = useState(false);
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);
  const [bulkProgress, setBulkProgress] = useState(0);
  const [bulkPreview, setBulkPreview] = useState<any>(null);
  const [bulkStatus, setBulkStatus] = useState<string>('');

  const bulkModes = [
    { value: 'plain', label: 'Plain Text (Case-insensitive)' },
    { value: 'plain_cs', label: 'Plain Text (Case-sensitive)' },
    { value: 'regex', label: 'Regular Expression' },
    { value: 'url', label: 'URL Operations' },
    { value: 'url_segment', label: 'URL Segment Replace' },
    { value: 'prefix_swap', label: 'Prefix Swap' },
    { value: 'full_text', label: 'Full Text Overwrite' }
  ];

  const handleEdit = (row: any) => {
    setEditingRow(row.post_id);
    setEditValue(row.meta_value);
    setUpdateMessage(null);
  };

  const handleSave = async (row: any) => {
    setIsUpdating(true);
    setUpdateMessage(null);
    
    try {
      await onUpdateRow({
        post_id: row.post_id,
        meta_key: row.meta_key,
        new_value: editValue
      });
      
      setUpdateMessage({
        type: 'success',
        text: 'Row updated successfully!'
      });
      
      setEditingRow(null);
      setEditValue('');
      
      // Clear message after 3 seconds
      setTimeout(() => setUpdateMessage(null), 3000);
      
    } catch (error) {
      setUpdateMessage({
        type: 'error',
        text: 'Failed to update row. Please try again.'
      });
    } finally {
      setIsUpdating(false);
    }
  };

  const handleCancel = () => {
    setEditingRow(null);
    setEditValue('');
    setUpdateMessage(null);
  };

  const handleRestore = async (row: any) => {
    try {
      await onRestoreRow({
        post_id: row.post_id,
        meta_key: row.meta_key
      });
    } catch (error) {
      console.error('Failed to restore row:', error);
    }
  };

  const handlePageChange = (newPage: number) => {
    if (onPageChange && newPage >= 1 && newPage <= (results?.total_pages || 1)) {
      onPageChange(newPage);
    }
  };

  // Bulk actions handlers
  const handleSelectAll = () => {
    if (results?.rows) {
      if (selectedRows.size === results.rows.length) {
        setSelectedRows(new Set());
      } else {
        const allKeys = results.rows.map(row => `${row.post_id}-${row.meta_key}`);
        setSelectedRows(new Set(allKeys));
      }
    }
  };

  const handleSelectRow = (rowKey: string) => {
    const newSelected = new Set(selectedRows);
    if (newSelected.has(rowKey)) {
      newSelected.delete(rowKey);
    } else {
      newSelected.add(rowKey);
    }
    setSelectedRows(newSelected);
  };

  const handleBulkPreview = async () => {
    if (!findText || !replaceText || selectedRows.size === 0) return;
    
    setIsBulkProcessing(true);
    setBulkProgress(0);
    
    try {
      // Get the first selected row to determine post_type and meta_key
      const firstSelectedRow = results.rows.find(row => 
        selectedRows.has(`${row.post_id}-${row.meta_key}`)
      );
      
      if (!firstSelectedRow) {
        throw new Error('No rows selected');
      }
      
      // Call the real preview API
      const { useDataReplacerStore } = await import('../store/dataReplacerStore');
      const store = useDataReplacerStore.getState();
      
      const previewParams = {
        find: findText,
        replace: replaceText,
        mode: bulkMode,
        post_type: firstSelectedRow.post_type,
        meta_key: firstSelectedRow.meta_key,
        case_sensitive: caseSensitive,
        limit: 5000 // Use high limit for bulk operations
      };
      
      const previewResults = await store.previewReplace(previewParams);
      
      setBulkPreview({
        totalRows: previewResults.total || 0,
        affectedRows: previewResults.rows || [],
        preview: previewResults,
        warnings: [],
        estimatedTime: 'Processing...'
      });
      
      setBulkProgress(100);
      
    } catch (error) {
      console.error('Bulk preview failed:', error);
      setBulkStatus('Preview failed: ' + (error as Error).message);
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const handleBulkExecute = async () => {
    if (!bulkPreview || !bulkPreview.affectedRows) return;
    
    setIsBulkProcessing(true);
    setBulkProgress(0);
    setBulkStatus('Preparing bulk operation...');
    
    try {
      const affectedRows = bulkPreview.affectedRows;
      
      if (affectedRows.length === 0) {
        alert('No changes to execute!');
        setIsBulkProcessing(false);
        return;
      }
      
      // Confirm execution
      const confirmMessage = `Are you sure you want to replace "${findText}" with "${replaceText}" in ${affectedRows.length} rows? This action cannot be undone.`;
      if (!confirm(confirmMessage)) {
        setIsBulkProcessing(false);
        setBulkStatus('');
        return;
      }
      
      // Get the first row to determine parameters
      const firstRow = results.rows.find(row => 
        selectedRows.has(`${row.post_id}-${row.meta_key}`)
      );
      
      if (!firstRow) {
        throw new Error('No rows selected');
      }
      
      // Call the real bulk replace API
      const { useDataReplacerStore } = await import('../store/dataReplacerStore');
      const store = useDataReplacerStore.getState();
      
      const replaceParams = {
        find: findText,
        replace: replaceText,
        mode: bulkMode,
        post_type: firstRow.post_type,
        meta_key: firstRow.meta_key,
        case_sensitive: caseSensitive,
        limit: 5000,
        confirm: true
      };
      
      setBulkStatus('Executing bulk replace...');
      setBulkProgress(50);
      
      const replaceResults = await store.executeReplace(replaceParams);
      
      setBulkProgress(100);
      setBulkStatus(`Bulk replace completed! Updated: ${replaceResults.updated}, Failed: ${replaceResults.failed}`);
      
      // Clear preview and reset
      setBulkPreview(null);
      setSelectedRows(new Set());
      
      // Refresh the search results
      if (onPageChange) {
        onPageChange(1);
      }
      
    } catch (error) {
      console.error('Bulk execute failed:', error);
      setBulkStatus('Bulk execute failed: ' + (error as Error).message);
      setTimeout(() => {
        alert(`Bulk execution failed: ${error}`);
        setIsBulkProcessing(false);
        setBulkStatus('');
      }, 2000);
    } finally {
      setIsBulkProcessing(false);
      setBulkProgress(0);
    }
  };

  const handleBulkCancel = () => {
    setShowBulkActions(false);
    setBulkPreview(null);
    setBulkProgress(0);
    setFindText('');
    setReplaceText('');
  };

  if (!results) {
    return (
      <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-12 wcfdr-text-center">
        <div className="wcfdr-text-gray-400 wcfdr-mb-4">
          <Eye className="wcfdr-h-12 wcfdr-w-12 wcfdr-mx-auto" />
        </div>
        <h3 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-2">
          No Results Yet
        </h3>
        <p className="wcfdr-text-gray-500">
          Use the search filters above to find post meta values.
        </p>
      </div>
    );
  }

  return (
    <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow">
      {/* Table Header with Bulk Actions */}
      <div className="wcfdr-px-6 wcfdr-py-4 wcfdr-border-b wcfdr-border-gray-200">
        <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
          <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-4">
            <h2 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900">
              Search Results
            </h2>
            
            {/* Bulk Actions Toggle */}
            {results.rows.length > 0 && (
              <button
                onClick={() => setShowBulkActions(!showBulkActions)}
                className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-1.5 wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-blue-700 wcfdr-bg-blue-100 hover:wcfdr-bg-blue-200"
              >
                <Zap className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
                Bulk Actions
                {selectedRows.size > 0 && (
                  <span className="wcfdr-ml-2 wcfdr-inline-flex wcfdr-items-center wcfdr-px-2 wcfdr-py-0.5 wcfdr-rounded-full wcfdr-text-xs wcfdr-font-medium wcfdr-bg-blue-600 wcfdr-text-white">
                    {selectedRows.size}
                  </span>
                )}
              </button>
            )}
          </div>
          
          <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-4">
            <span className="wcfdr-text-sm wcfdr-text-gray-500">
              {results.total} total results
            </span>
            <span className="wcfdr-text-sm wcfdr-text-gray-500">
              Page {results.page} of {results.total_pages}
            </span>
          </div>
        </div>
      </div>

      {/* Bulk Actions Panel */}
      {showBulkActions && (
        <div className="wcfdr-px-6 wcfdr-py-4 wcfdr-bg-gray-50 wcfdr-border-b wcfdr-border-gray-200">
          <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-4 lg:wcfdr-grid-cols-2">
            {/* Configuration */}
            <div className="wcfdr-space-y-3">
              <div className="wcfdr-grid wcfdr-grid-cols-2 wcfdr-gap-3">
                <div>
                  <label className="wcfdr-block wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-1">
                    Find
                  </label>
                  <input
                    value={findText}
                    onChange={(e) => setFindText(e.target.value)}
                    placeholder="Text to find..."
                    className="wcfdr-w-full wcfdr-text-sm wcfdr-font-mono wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                  />
                </div>
                <div>
                  <label className="wcfdr-block wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-1">
                    Replace With
                  </label>
                  <input
                    value={replaceText}
                    onChange={(e) => setReplaceText(e.target.value)}
                    placeholder="Replacement text..."
                    className="wcfdr-w-full wcfdr-text-sm wcfdr-font-mono wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                  />
                </div>
              </div>
              
              <div className="wcfdr-grid wcfdr-grid-cols-2 wcfdr-gap-3">
                <div>
                  <label className="wcfdr-block wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-1">
                    Mode
                  </label>
                  <select
                    value={bulkMode}
                    onChange={(e) => setBulkMode(e.target.value)}
                    className="wcfdr-w-full wcfdr-text-sm wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                  >
                    {bulkModes.map((m) => (
                      <option key={m.value} value={m.value}>
                        {m.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="wcfdr-flex wcfdr-items-end">
                  <label className="wcfdr-flex wcfdr-items-center wcfdr-text-xs wcfdr-text-gray-700">
                    <input
                      type="checkbox"
                      checked={caseSensitive}
                      onChange={(e) => setCaseSensitive(e.target.checked)}
                      disabled={bulkMode === 'plain'}
                      className="wcfdr-mr-2 wcfdr-rounded wcfdr-border-gray-300 wcfdr-text-blue-600 focus:wcfdr-ring-blue-500"
                    />
                    Case Sensitive
                  </label>
                </div>
              </div>
            </div>

            {/* Actions */}
            <div className="wcfdr-flex wcfdr-items-end wcfdr-space-x-2">
              <button
                onClick={handleBulkPreview}
                disabled={!findText || !replaceText || selectedRows.size === 0 || isBulkProcessing}
                className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-border wcfdr-border-transparent wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-blue-600 hover:wcfdr-bg-blue-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
              >
                <Eye className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
                Preview
              </button>
              
              <button
                onClick={handleBulkCancel}
                className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
              >
                <X className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
                Cancel
              </button>
            </div>
          </div>

          {/* Progress Bar */}
          {isBulkProcessing && (
            <div className="wcfdr-mt-3 wcfdr-bg-blue-50 wcfdr-border wcfdr-border-blue-200 wcfdr-rounded-lg wcfdr-p-3">
              <div className="wcfdr-flex wcfdr-items-center wcfdr-mb-2">
                <div className="wcfdr-animate-spin wcfdr-rounded-full wcfdr-h-4 wcfdr-w-4 wcfdr-border-b-2 wcfdr-border-blue-600 wcfdr-mr-2"></div>
                <span className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-blue-800">
                  {bulkPreview ? 'Executing changes...' : 'Previewing changes...'}
                </span>
              </div>
              <div className="wcfdr-w-full wcfdr-bg-blue-200 wcfdr-rounded-full wcfdr-h-2">
                <div 
                  className="wcfdr-bg-blue-600 wcfdr-h-2 wcfdr-rounded-full wcfdr-transition-all wcfdr-duration-300"
                  style={{ width: `${bulkProgress}%` }}
                ></div>
              </div>
              <div className="wcfdr-text-xs wcfdr-text-blue-600 wcfdr-mt-1">
                {bulkProgress}% complete
              </div>
              {bulkStatus && (
                <div className="wcfdr-text-sm wcfdr-text-blue-700 wcfdr-mt-2 wcfdr-text-center wcfdr-font-medium">
                  {bulkStatus}
                </div>
              )}
            </div>
          )}

          {/* Preview Results */}
          {bulkPreview && !isBulkProcessing && (
            <div className="wcfdr-mt-3 wcfdr-bg-green-50 wcfdr-border wcfdr-border-green-200 wcfdr-rounded-lg wcfdr-p-4">
              <div className="wcfdr-space-y-4">
                {/* Summary */}
                <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
                  <div className="wcfdr-space-y-1">
                    <div className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-green-800">
                      Preview: {bulkPreview.affectedRows} of {bulkPreview.totalRows} rows will be affected
                    </div>
                    <div className="wcfdr-text-xs wcfdr-text-green-600">
                      Mode: {bulkModes.find(m => m.value === bulkPreview.mode)?.label} | 
                      Estimated time: {bulkPreview.estimatedTime}
                    </div>
                    <div className="wcfdr-text-xs wcfdr-text-green-600">
                      Find: "{bulkPreview.findText}" → Replace: "{bulkPreview.replaceText}"
                    </div>
                  </div>
                  <button
                    onClick={handleBulkExecute}
                    className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-1.5 wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-green-600 hover:wcfdr-bg-green-700"
                  >
                    <Play className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-1" />
                    Execute
                  </button>
                </div>

                {/* Warnings */}
                {bulkPreview.warnings.length > 0 && (
                  <div className="wcfdr-bg-yellow-50 wcfdr-border wcfdr-border-yellow-200 wcfdr-rounded-md wcfdr-p-3">
                    <div className="wcfdr-flex">
                      <AlertTriangle className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-yellow-400 wcfdr-mr-2 wcfdr-mt-0.5" />
                      <div className="wcfdr-text-sm wcfdr-text-yellow-800">
                        <strong>Warnings:</strong>
                        <ul className="wcfdr-mt-1 wcfdr-list-disc wcfdr-list-inside">
                          {bulkPreview.warnings.map((warning: string, index: number) => (
                            <li key={index}>{warning}</li>
                          ))}
                        </ul>
                      </div>
                    </div>
                  </div>
                )}

                {/* Detailed Preview Table */}
                {bulkPreview.previewData && bulkPreview.previewData.length > 0 && (
                  <div className="wcfdr-bg-white wcfdr-border wcfdr-border-green-200 wcfdr-rounded-md wcfdr-overflow-hidden">
                    <div className="wcfdr-px-3 wcfdr-py-2 wcfdr-bg-green-100 wcfdr-border-b wcfdr-border-green-200">
                      <h4 className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-green-800">
                        Affected Rows Preview
                      </h4>
                    </div>
                    <div className="wcfdr-max-h-60 wcfdr-overflow-y-auto">
                      <table className="wcfdr-min-w-full wcfdr-divide-y wcfdr-divide-green-200">
                        <thead className="wcfdr-bg-green-50">
                          <tr>
                            <th className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-green-700">
                              Post
                            </th>
                            <th className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-green-700">
                              Meta Key
                            </th>
                            <th className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-green-700">
                              Before
                            </th>
                            <th className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-green-700">
                              After
                            </th>
                          </tr>
                        </thead>
                        <tbody className="wcfdr-bg-white wcfdr-divide-y wcfdr-divide-green-200">
                          {bulkPreview.previewData.slice(0, 10).map((row, index) => (
                            <tr key={index} className="hover:wcfdr-bg-green-50">
                              <td className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-xs wcfdr-text-green-900">
                                <div className="wcfdr-font-medium">{row.post_title}</div>
                                <div className="wcfdr-text-green-600">ID: {row.post_id}</div>
                              </td>
                              <td className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-xs wcfdr-text-green-900 wcfdr-font-mono">
                                {row.meta_key}
                              </td>
                              <td className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-xs wcfdr-text-green-900 wcfdr-font-mono wcfdr-max-w-xs wcfdr-break-words">
                                <div className="wcfdr-bg-red-50 wcfdr-p-1 wcfdr-rounded wcfdr-border wcfdr-border-red-200">
                                  {row.old_value}
                                </div>
                              </td>
                              <td className="wcfdr-px-3 wcfdr-py-2 wcfdr-text-xs wcfdr-text-green-900 wcfdr-font-mono wcfdr-max-w-xs wcfdr-break-words">
                                <div className="wcfdr-bg-green-50 wcfdr-p-1 wcfdr-rounded wcfdr-border wcfdr-border-green-200">
                                  {row.new_value}
                                </div>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                      
                      {/* Show more indicator */}
                      {bulkPreview.previewData.length > 10 && (
                        <div className="wcfdr-px-3 wcfdr-py-2 wcfdr-bg-green-50 wcfdr-border-t wcfdr-border-green-200 wcfdr-text-center">
                          <span className="wcfdr-text-xs wcfdr-text-green-600">
                            Showing first 10 of {bulkPreview.previewData.length} affected rows
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                )}

                {/* No Changes Message */}
                {bulkPreview.affectedRows === 0 && (
                  <div className="wcfdr-bg-blue-50 wcfdr-border wcfdr-border-blue-200 wcfdr-rounded-md wcfdr-p-3 wcfdr-text-center">
                    <div className="wcfdr-text-sm wcfdr-text-blue-800">
                      <strong>No changes detected!</strong> The search criteria didn't match any of the selected rows.
                    </div>
                    <div className="wcfdr-text-xs wcfdr-text-blue-600 wcfdr-mt-1">
                      Try adjusting your search terms or selecting different rows.
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Update Message */}
      {updateMessage && (
        <div className={`wcfdr-mx-6 wcfdr-mt-4 wcfdr-p-3 wcfdr-rounded-lg wcfdr-border ${
          updateMessage.type === 'success' 
            ? 'wcfdr-bg-green-50 wcfdr-border-green-200 wcfdr-text-green-800' 
            : 'wcfdr-bg-red-50 wcfdr-border-red-200 wcfdr-text-red-800'
        }`}>
          {updateMessage.text}
        </div>
      )}

      {/* Results Table */}
      <div className="wcfdr-overflow-x-auto">
        <table className="wcfdr-min-w-full wcfdr-divide-y wcfdr-divide-gray-200">
          <thead className="wcfdr-bg-gray-50">
            <tr>
              {/* Select All Checkbox */}
              <th className="wcfdr-px-6 wcfdr-py-3 wcfdr-text-left">
                <button
                  onClick={handleSelectAll}
                  className="wcfdr-text-gray-400 hover:wcfdr-text-gray-600"
                >
                  {results.rows.length > 0 && selectedRows.size === results.rows.length ? (
                    <CheckSquare className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-blue-600" />
                  ) : (
                    <Square className="wcfdr-h-4 wcfdr-w-4" />
                  )}
                </button>
              </th>
              
              <th className="wcfdr-px-6 wcfdr-py-3 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-500 wcfdr-uppercase wcfdr-tracking-wider">
                Post Title
              </th>
              <th className="wcfdr-px-6 wcfdr-py-3 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-500 wcfdr-uppercase wcfdr-tracking-wider">
                Meta Key
              </th>
              <th className="wcfdr-px-6 wcfdr-py-3 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-500 wcfdr-uppercase wcfdr-tracking-wider">
                Meta Value
              </th>
              <th className="wcfdr-px-6 wcfdr-py-3 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-500 wcfdr-uppercase wcfdr-tracking-wider">
                Status
              </th>
              <th className="wcfdr-px-6 wcfdr-py-3 wcfdr-text-left wcfdr-text-xs wcfdr-font-medium wcfdr-text-gray-500 wcfdr-uppercase wcfdr-tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="wcfdr-bg-white wcfdr-divide-y wcfdr-divide-gray-200">
            {results.rows.map((row) => {
              const rowKey = `${row.post_id}-${row.meta_key}`;
              const isSelected = selectedRows.has(rowKey);
              
              return (
                <tr key={rowKey} className={`hover:wcfdr-bg-gray-50 ${isSelected ? 'wcfdr-bg-blue-50' : ''}`}>
                  {/* Row Selection Checkbox */}
                  <td className="wcfdr-px-6 wcfdr-py-4 wcfdr-whitespace-nowrap">
                    <button
                      onClick={() => handleSelectRow(rowKey)}
                      className="wcfdr-text-gray-400 hover:wcfdr-text-gray-600"
                    >
                      {isSelected ? (
                        <CheckSquare className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-blue-600" />
                      ) : (
                        <Square className="wcfdr-h-4 wcfdr-w-4" />
                      )}
                    </button>
                  </td>

                  {/* Post Title */}
                  <td className="wcfdr-px-6 wcfdr-py-4 wcfdr-whitespace-nowrap">
                    <div className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900">
                      {row.post_title}
                    </div>
                    <div className="wcfdr-text-sm wcfdr-text-gray-500">
                      ID: {row.post_id}
                    </div>
                  </td>

                  {/* Meta Key */}
                  <td className="wcfdr-px-6 wcfdr-py-4 wcfdr-whitespace-nowrap">
                    <div className="wcfdr-text-sm wcfdr-text-gray-900 wcfdr-font-mono">
                      {row.meta_key}
                    </div>
                  </td>

                  {/* Meta Value */}
                  <td className="wcfdr-px-6 wcfdr-py-4">
                    {editingRow === row.post_id ? (
                      <div className="wcfdr-space-y-2">
                        <textarea
                          value={editValue}
                          onChange={(e) => setEditValue(e.target.value)}
                          rows={3}
                          className="wcfdr-w-full wcfdr-text-sm wcfdr-font-mono wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
                        />
                        <div className="wcfdr-flex wcfdr-space-x-2">
                          <button
                            onClick={() => handleSave(row)}
                            disabled={isUpdating}
                            className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2 wcfdr-py-1 wcfdr-text-xs wcfdr-font-medium wcfdr-rounded wcfdr-text-white wcfdr-bg-green-600 hover:wcfdr-bg-green-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
                          >
                            {isUpdating ? 'Saving...' : 'Save'}
                          </button>
                          <button
                            onClick={handleCancel}
                            disabled={isUpdating}
                            className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2 wcfdr-py-1 wcfdr-text-xs wcfdr-font-medium wcfdr-rounded wcfdr-text-gray-700 wcfdr-bg-gray-100 hover:wcfdr-bg-gray-200 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
                          >
                            Cancel
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="wcfdr-text-sm wcfdr-text-gray-900 wcfdr-font-mono wcfdr-max-w-xs wcfdr-break-words">
                        {row.meta_value}
                      </div>
                    )}
                  </td>

                  {/* Status */}
                  <td className="wcfdr-px-6 wcfdr-py-4 wcfdr-whitespace-nowrap">
                    <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
                      {row.is_modified && (
                        <span className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2.5 wcfdr-py-0.5 wcfdr-rounded-full wcfdr-text-xs wcfdr-font-medium wcfdr-bg-yellow-100 wcfdr-text-yellow-800">
                          <AlertTriangle className="wcfdr-h-3 wcfdr-w-3 wcfdr-mr-1" />
                          Modified
                        </span>
                      )}
                      {row.has_backup && (
                        <span className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2.5 wcfdr-py-0.5 wcfdr-rounded-full wcfdr-text-xs wcfdr-font-medium wcfdr-bg-green-100 wcfdr-text-green-800">
                          <CheckCircle className="wcfdr-h-3 wcfdr-w-3 wcfdr-mr-1" />
                          Backup
                        </span>
                      )}
                    </div>
                  </td>

                  {/* Actions */}
                  <td className="wcfdr-px-6 wcfdr-py-4 wcfdr-whitespace-nowrap wcfdr-text-sm wcfdr-font-medium">
                    <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
                      {editingRow !== row.post_id ? (
                        <button
                          onClick={() => handleEdit(row)}
                          disabled={isUpdating}
                          className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2 wcfdr-py-1 wcfdr-text-xs wcfdr-font-medium wcfdr-rounded wcfdr-text-blue-700 wcfdr-bg-blue-100 hover:wcfdr-bg-blue-200 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
                        >
                          <Edit className="wcfdr-h-3 wcfdr-w-3 wcfdr-mr-1" />
                          Edit
                        </button>
                      ) : null}
                      
                      {row.has_backup && (
                        <button
                          onClick={() => handleRestore(row)}
                          disabled={isUpdating}
                          className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2 wcfdr-py-1 wcfdr-text-xs wcfdr-font-medium wcfdr-rounded wcfdr-text-orange-700 wcfdr-bg-orange-100 hover:wcfdr-bg-orange-200 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
                        >
                          <RotateCcw className="wcfdr-h-3 wcfdr-w-3 wcfdr-mr-1" />
                          Restore
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {results.total_pages > 1 && (
        <div className="wcfdr-px-6 wcfdr-py-4 wcfdr-border-t wcfdr-border-gray-200">
          <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
            <div className="wcfdr-text-sm wcfdr-text-gray-700">
              Showing page {results.page} of {results.total_pages} 
              ({results.total} total results)
            </div>
            <div className="wcfdr-flex wcfdr-space-x-2">
              <button
                onClick={() => handlePageChange(results.page - 1)}
                disabled={results.page <= 1}
                className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
              >
                <ChevronLeft className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-1" />
                Previous
              </button>
              <button
                onClick={() => handlePageChange(results.page + 1)}
                disabled={results.page >= results.total_pages}
                className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
              >
                Next
                <ChevronRight className="wcfdr-h-4 wcfdr-w-4 wcfdr-ml-1" />
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
