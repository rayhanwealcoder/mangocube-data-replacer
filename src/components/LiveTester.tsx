import React, { useState, useEffect } from 'react';
import { TestTube, Play, RotateCcw, AlertTriangle, CheckCircle, Copy, Zap, Eye } from 'lucide-react';

interface TestResult {
  matches: number;
  preview: string;
  warnings: string[];
  error?: string;
  executionTime: number;
}

export const LiveTester: React.FC = () => {
  const [findText, setFindText] = useState('');
  const [replaceText, setReplaceText] = useState('');
  const [mode, setMode] = useState('plain');
  const [caseSensitive, setCaseSensitive] = useState(false);
  const [sampleText, setSampleText] = useState('');
  const [testResult, setTestResult] = useState<TestResult | null>(null);
  const [isTesting, setIsTesting] = useState(false);

  const modes = [
    { value: 'plain', label: 'Plain Text (Case-insensitive)' },
    { value: 'plain_cs', label: 'Plain Text (Case-sensitive)' },
    { value: 'regex', label: 'Regular Expression' },
    { value: 'url', label: 'URL Operations' },
    { value: 'url_segment', label: 'URL Segment Replace' },
    { value: 'prefix_swap', label: 'Prefix Swap' },
    { value: 'full_text', label: 'Full Text Overwrite' }
  ];

  const handleTest = async () => {
    if (!findText || !sampleText) return;

    setIsTesting(true);
    try {
      // Simulate testing - in real implementation, this would call the backend
      await new Promise(resolve => setTimeout(resolve, 500));
      
      const result = performTest(findText, replaceText, sampleText, mode, caseSensitive);
      setTestResult(result);
    } catch (error) {
      setTestResult({
        matches: 0,
        preview: sampleText,
        warnings: [],
        error: 'Test failed',
        executionTime: 0
      });
    } finally {
      setIsTesting(false);
    }
  };

  const performTest = (find: string, replace: string, sample: string, mode: string, caseSensitive: boolean): TestResult => {
    const startTime = performance.now();
    let matches = 0;
    let preview = sample;
    let warnings: string[] = [];

    try {
      switch (mode) {
        case 'plain':
        case 'plain_cs':
          const flags = caseSensitive ? 'g' : 'gi';
          const regex = new RegExp(escapeRegex(find), flags);
          matches = (sample.match(regex) || []).length;
          preview = sample.replace(regex, replace);
          break;
          
        case 'regex':
          try {
            const regex = new RegExp(find, caseSensitive ? 'g' : 'gi');
            matches = (sample.match(regex) || []).length;
            preview = sample.replace(regex, replace);
          } catch (error) {
            throw new Error('Invalid regex pattern');
          }
          break;
          
        case 'url':
          if (sample.startsWith('http')) {
            if (find.includes('://')) {
              // Domain replacement
              const url = new URL(sample);
              const newUrl = new URL(replace);
              url.protocol = newUrl.protocol;
              url.host = newUrl.host;
              preview = url.toString();
              matches = 1;
            } else {
              // Path replacement
              const url = new URL(sample);
              url.pathname = replace;
              preview = url.toString();
              matches = 1;
            }
          } else {
            warnings.push('Sample text does not appear to be a URL');
          }
          break;
          
        case 'full_text':
          preview = replace;
          matches = 1;
          break;
          
        default:
          preview = sample;
      }
    } catch (error) {
      throw error;
    }

    const executionTime = performance.now() - startTime;

    return {
      matches,
      preview,
      warnings,
      executionTime
    };
  };

  const escapeRegex = (string: string) => {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  };

  const handleClear = () => {
    setFindText('');
    setReplaceText('');
    setSampleText('');
    setTestResult(null);
  };

  const handleLoadSample = () => {
    setSampleText('https://example.com/old-path/page?param=value#section');
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
  };

  return (
    <div className="wcfdr-bg-white wcfdr-rounded-lg wcfdr-shadow wcfdr-p-6">
      <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between wcfdr-mb-6">
        <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2">
          <TestTube className="wcfdr-h-6 wcfdr-w-6 wcfdr-text-blue-600" />
          <h2 className="wcfdr-text-xl wcfdr-font-semibold wcfdr-text-gray-900">
            Live Tester
          </h2>
        </div>
        <span className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-2.5 wcfdr-py-0.5 wcfdr-rounded-full wcfdr-text-xs wcfdr-font-medium wcfdr-bg-blue-100 wcfdr-text-blue-800">
          Test your replacements in real-time
        </span>
      </div>

      <div className="wcfdr-grid wcfdr-grid-cols-1 wcfdr-gap-6 lg:wcfdr-grid-cols-2">
        {/* Input Section */}
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

          <div>
            <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
              Sample Text
            </label>
            <textarea
              value={sampleText}
              onChange={(e) => setSampleText(e.target.value)}
              placeholder="Enter sample text to test..."
              rows={6}
              className="wcfdr-w-full wcfdr-font-mono wcfdr-rounded-md wcfdr-border-gray-300 wcfdr-shadow-sm focus:wcfdr-border-blue-500 focus:wcfdr-ring-blue-500"
            />
            <div className="wcfdr-mt-2 wcfdr-flex wcfdr-space-x-2">
              <button
                onClick={handleLoadSample}
                className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-1.5 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-xs wcfdr-font-medium wcfdr-rounded wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
              >
                Load Sample URL
              </button>
            </div>
          </div>

          <div className="wcfdr-flex wcfdr-space-x-3">
            <button
              onClick={handleTest}
              disabled={!findText || !sampleText || isTesting}
              className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-transparent wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-white wcfdr-bg-blue-600 hover:wcfdr-bg-blue-700 disabled:wcfdr-opacity-50 disabled:wcfdr-cursor-not-allowed"
            >
              <Play className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
              {isTesting ? 'Testing...' : 'Test'}
            </button>
            
            <button
              onClick={handleClear}
              className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
            >
              <RotateCcw className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
              Clear
            </button>
          </div>
        </div>

        {/* Results Section */}
        <div className="wcfdr-space-y-4">
          <div className="wcfdr-bg-gray-50 wcfdr-rounded-lg wcfdr-p-4">
            <h3 className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900 wcfdr-mb-3">
              Test Results
            </h3>
            
            {testResult ? (
              <div className="wcfdr-space-y-4">
                {/* Match Count */}
                <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
                  <span className="wcfdr-text-sm wcfdr-text-gray-600">Matches Found:</span>
                  <span className={`wcfdr-inline-flex wcfdr-items-center wcfdr-px-2.5 wcfdr-py-0.5 wcfdr-rounded-full wcfdr-text-xs wcfdr-font-medium ${
                    testResult.matches > 0 
                      ? 'wcfdr-bg-green-100 wcfdr-text-green-800' 
                      : 'wcfdr-bg-gray-100 wcfdr-text-gray-800'
                  }`}>
                    {testResult.matches} match{testResult.matches !== 1 ? 'es' : ''}
                  </span>
                </div>

                {/* Execution Time */}
                <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
                  <span className="wcfdr-text-sm wcfdr-text-gray-600">Execution Time:</span>
                  <span className="wcfdr-text-sm wcfdr-text-gray-900">
                    {testResult.executionTime.toFixed(2)}ms
                  </span>
                </div>

                {/* Warnings */}
                {testResult.warnings.length > 0 && (
                  <div className="wcfdr-bg-yellow-50 wcfdr-border wcfdr-border-yellow-200 wcfdr-rounded-md wcfdr-p-3">
                    <div className="wcfdr-flex">
                      <AlertTriangle className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-yellow-400 wcfdr-mr-2" />
                      <div className="wcfdr-text-sm wcfdr-text-yellow-800">
                        <strong>Warnings:</strong>
                        <ul className="wcfdr-mt-1 wcfdr-list-disc wcfdr-list-inside">
                          {testResult.warnings.map((warning, index) => (
                            <li key={index}>{warning}</li>
                          ))}
                        </ul>
                      </div>
                    </div>
                  </div>
                )}

                {/* Error */}
                {testResult.error && (
                  <div className="wcfdr-bg-red-50 wcfdr-border wcfdr-border-red-200 wcfdr-rounded-md wcfdr-p-3">
                    <div className="wcfdr-flex">
                      <AlertTriangle className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-red-400 wcfdr-mr-2" />
                      <div className="wcfdr-text-sm wcfdr-text-red-800">
                        <strong>Error:</strong> {testResult.error}
                      </div>
                    </div>
                  </div>
                )}

                {/* Preview */}
                <div>
                  <label className="wcfdr-block wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-700 wcfdr-mb-2">
                    Result Preview
                  </label>
                  <div className="wcfdr-bg-white wcfdr-border wcfdr-border-gray-200 wcfdr-rounded-md wcfdr-p-3 wcfdr-relative">
                    <div className="wcfdr-text-sm wcfdr-text-gray-900 wcfdr-font-mono wcfdr-break-all">
                      {testResult.preview}
                    </div>
                    <button
                      onClick={() => copyToClipboard(testResult.preview)}
                      className="wcfdr-absolute wcfdr-top-2 wcfdr-right-2 wcfdr-text-gray-400 hover:wcfdr-text-gray-600"
                    >
                      <Copy className="wcfdr-h-4 wcfdr-w-4" />
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <div className="wcfdr-text-center wcfdr-text-gray-500 wcfdr-py-8">
                <Zap className="wcfdr-h-12 wcfdr-w-12 wcfdr-mx-auto wcfdr-mb-4 wcfdr-text-gray-300" />
                <p>Run a test to see results here</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
