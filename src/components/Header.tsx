import React from 'react';
import { HelpCircle, Settings, Shield, Zap } from 'lucide-react';

interface HeaderProps {
  onSettingsClick: () => void;
  onHelpClick: () => void;
}

export const Header: React.FC<HeaderProps> = ({ onSettingsClick, onHelpClick }) => {
  return (
    <header className="wcfdr-bg-white wcfdr-border-b wcfdr-border-gray-200 wcfdr-px-6 wcfdr-py-4">
      <div className="wcfdr-flex wcfdr-items-center wcfdr-justify-between">
        <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-4">
          <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-3">
            <div className="wcfdr-bg-blue-600 wcfdr-p-2 wcfdr-rounded-lg">
              <Zap className="wcfdr-h-6 wcfdr-w-6 wcfdr-text-white" />
            </div>
            <div>
              <h1 className="wcfdr-text-2xl wcfdr-font-bold wcfdr-text-gray-900">
                WCF Data Replacer
              </h1>
              <p className="wcfdr-text-sm wcfdr-text-gray-600">
                Professional post meta search, preview, and replacement tool
              </p>
            </div>
          </div>
        </div>

        <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-4">
          {/* Status Badge */}
          <div className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-1.5 wcfdr-rounded-full wcfdr-text-sm wcfdr-font-medium wcfdr-bg-green-100 wcfdr-text-green-800">
            <div className="wcfdr-w-2 wcfdr-h-2 wcfdr-bg-green-400 wcfdr-rounded-full wcfdr-mr-2"></div>
            Ready
          </div>

          {/* Help Button */}
          <button 
            onClick={onHelpClick}
            className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
          >
            <HelpCircle className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
            Help
          </button>

          {/* Settings Button */}
          <button 
            onClick={onSettingsClick}
            className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
          >
            <Settings className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
            Settings
          </button>

          {/* Security Indicator */}
          <div className="wcfdr-inline-flex wcfdr-items-center wcfdr-px-3 wcfdr-py-2 wcfdr-bg-blue-50 wcfdr-text-blue-700 wcfdr-rounded-md wcfdr-text-sm wcfdr-font-medium">
            <Shield className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
            Admin Only
          </div>
        </div>
      </div>
    </header>
  );
};
