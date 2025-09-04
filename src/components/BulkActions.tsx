import React, { useState } from 'react';
import { Play, AlertTriangle, CheckCircle, RotateCcw, Download, Upload, Zap, Eye } from 'lucide-react';

export const BulkActions: React.FC = () => {
  const [isPreviewMode, setIsPreviewMode] = useState(false);
  const [findText, setFindText] = useState('');
  const [replaceText, setReplaceText] = useState('');
  const [mode, setMode] = useState('plain');
  const [caseSensitive, setCaseSensitive] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [progress, setProgress] = useState(0);
  const [previewResults, setPreviewResults] = useState<any>(null);

  const modes = [
    { value: 'plain', label: 'Plain Text (Case-insensitive)' },
    { value: 'plain_cs', label: 'Plain Text (Case-sensitive)' },
    { value: 'regex', label: 'Regular Expression' },
    { value: 'url', label: 'URL Operations' },
    { value: 'url_segment', label: 'URL Segment Replace' },
    { value: 'prefix_swap', label: 'Prefix Swap' },
    { value: 'full_text', label: 'Full Text Overwrite' }
  ];

  const handlePreview = async () => {
    if (!findText || !replaceText) return;
    
    setIsPreviewMode(true);
    setIsProcessing(true);
    
    // Simulate preview processing
    for (let i = 0; i <= 100; i += 10) {
      setProgress(i);
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    
    // Mock preview results
    setPreviewResults({
      totalRows: 45,
      affectedRows: 23,
      preview: 'Sample preview of changes...',
      warnings: ['Some rows may have conflicts'],
      estimatedTime: '2.5 seconds'
    });
    
    setIsProcessing(false);
  };

  const handleExecute = async () => {
    if (!previewResults) return;
    
    setIsProcessing(true);
    setProgress(0);
    
    // Simulate execution
    for (let i = 0; i <= 100; i += 5) {
      setProgress(i);
      await new Promise(resolve => setTimeout(resolve, 200));
    }
    
    setIsProcessing(false);
    // Show success message
  };

  const handleCancel = () => {
    setIsPreviewMode(false);
    setProgress(0);
    setPreviewResults(null);
  };

  return (
    <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-6">
      <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between wcfdr-mb-6">
        <h2 className="wcfdr-text-lg wcfdr-font-medium wcfdr-text-gray-900">
          Bulk Actions
        </h2>
        <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
          <Zap className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-yellow-500" />
          <span className="wcfdr-text-sm wcfdr-text-gray-500">Bulk Replace Operations</span>
        </div>
      </div>

      <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-6 lg:wcfdr-grid-cols-2">
        {/* Configuration */}
        <div className="wcfdr-space-y-4">
          <div>
            <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
              Find
            </label>
            <input
              value={findText}
              onChange={(e) => setFindText(e.target.value)}
              placeholder="Text to find..."
              className="wcfdr-w-full wcfdr-font-mono wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
            />
          </div>

          <div>
            <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
              Replace With
            </label>
            <input
              value={replaceText}
              onChange={(e) => setReplaceText(e.target.value)}
              placeholder="Replacement text..."
              className="wcfdr-w-full wcfdr-font-mono wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
            />
          </div>

          <div>
            <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
              Mode
            </label>
            <select
              value={mode}
              onChange={(e) => setMode(e.target.value)}
              className="wcfdr-w-full wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
            >
              {modes.map((m) => (
                <option key={m.value} value={m.value}>
                  {m.label}
                </option>
              ))}
            </select>
          </div>

          <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
            <label className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700">
              Case Sensitive
            </label>
            <input
              type="checkbox"
              checked={caseSensitive}
              onChange={(e) => setCaseSensitive(e.target.checked)}
              disabled={mode === 'plain'}
              className="wcfdr-rounded wcfdr-border-gray-300 wcfdr-text-blue-600 focus:wcfdr-ring-blue-500"
            />
          </div>

          <div className="wcfdr-flex wcfdr-space-x-3">
            <button
              onClick={handlePreview}
              disabled={!findText || !replaceText || isProcessing}
              className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-transparent wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-blue-600 hover:wcfdr-bg-blue-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
            >
              <Eye className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
              Preview Changes
            </button>
            
            <button
              onClick={handleCancel}
              className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
            >
              <RotateCcw className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
              Cancel
            </button>
          </div>
        </div>

        {/* Preview & Execution */}
        <div className="wcfdr-space-y-4">
          {isProcessing && (
            <div className="wcfdr-bg-blue-50 wcfdr-border wcfdr-border-blue-200 wcfdr-rounded-lg wcfdr-p-4">
              <div className="wcfdr-flex wcfdr-items-center wcfdr-mb-3">
                <div className="wcfdr-animate-spin wcfdr-rounded-full wcfdr-h-4 wcfdr-w-4 wcfdr-border-b-2 wcfdr-border-blue-600 wcfdr-mr-2"></div>
                <span className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-blue-800">
                  {isPreviewMode ? 'Previewing changes...' : 'Executing changes...'}
                </span>
              </div>
              <div className="wcfdr-w-full wcfdr-bg-blue-200 wcfdr-rounded-full wcfdr-h-2">
                <div 
                  className="wcfdr-bg-blue-600 wcfdr-h-2 wcfdr-rounded-full wcfdr-transition-all wcfdr-duration-300"
                  style={{ width: `${progress}%` }}
                ></div>
              </div>
              <div className="wcfdr-text-xs wcfdr-text-blue-600 wcfdr-mt-2">
                {progress}% complete
              </div>
            </div>
          )}

          {previewResults && !isProcessing && (
            <div className="wcfdr-bg-gray-50 wcfdr-border wcfdr-border-gray-200 wcfdr-rounded-lg wcfdr-p-4">
              <h3 className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-3">
                Preview Results
              </h3>
              
              <div className="wcfdr-space-y-3">
                <div className="wcfdr-flex wcfdr-justify-between">
                  <span className="wcfdr-text-sm wcfdr-text-gray-600">Total Rows:</span>
                  <span className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900">
                    {previewResults.totalRows}
                  </span>
                </div>
                
                <div className="wcfdr-flex wcfdr-justify-between">
                  <span className="wcfdr-text-sm wcfdr-text-gray-600">Affected Rows:</span>
                  <span className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900">
                    {previewResults.affectedRows}
                  </span>
                </div>
                
                <div className="wcfdr-flex wcfdr-justify-between">
                  <span className="wcfdr-text-sm wcfdr-text-gray-600">Estimated Time:</span>
                  <span className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900">
                    {previewResults.estimatedTime}
                  </span>
                </div>
              </div>

              {previewResults.warnings.length > 0 && (
                <div className="wcfdr-mt-3 wcfdr-bg-yellow-50 wcfdr-border wcfdr-border-yellow-200 wcfdr-rounded-md wcfdr-p-3">
                  <div className="wcfdr-flex">
                    <AlertTriangle className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-yellow-400 wcfdr-mr-2" />
                    <div className="wcfdr-text-sm wcfdr-text-yellow-800">
                      <strong>Warnings:</strong>
                      <ul className="wcfdr-mt-1 wcfdr-list-disc wcfdr-list-inside">
                        {previewResults.warnings.map((warning: string, index: number) => (
                          <li key={index}>{warning}</li>
                        ))}
                      </ul>
                    </div>
                  </div>
                </div>
              )}

              <div className="wcfdr-mt-4">
                <button
                  onClick={handleExecute}
                  disabled={isProcessing}
                  className="wcfdr-w-full wcfdr-inline-flex wcfdr-items-center wcfdr-justify-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-transparent wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-green-600 hover:wcfdr-bg-green-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
                >
                  <Play className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
                  Execute Changes
                </button>
              </div>
            </div>
          )}

          {!previewResults && !isProcessing && (
            <div className="wcfdr-bg-gray-50 wcfdr-border wcfdr-border-gray-200 wcfdr-rounded-lg wcfdr-p-8 wcfdr-text-center">
              <div className="wcfdr-text-gray-400 wcfdr-mb-4">
                <Zap className="wcfdr-h-12 wcfdr-w-12 wcfdr-mx-auto" />
              </div>
              <p className="wcfdr-text-gray-500">
                Configure your replacement and click "Preview Changes" to see what will be affected
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
