import React from 'react';
import { Search, TestTube, Database, Settings, HelpCircle, Activity } from 'lucide-react';

interface SidebarProps {
  activeTab: 'search' | 'live-tester' | 'backups' | 'settings' | 'help';
  onTabChange: (tab: 'search' | 'live-tester' | 'backups' | 'settings' | 'help') => void;
  onSettingsClick: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({ activeTab, onTabChange, onSettingsClick }) => {
  const tabs = [
    {
      id: 'search' as const,
      label: 'Search & Replace',
      icon: Search,
      description: 'Search and replace post meta values'
    },
    {
      id: 'live-tester' as const,
      label: 'Live Tester',
      icon: TestTube,
      description: 'Test replacements in real-time'
    },
    {
      id: 'backups' as const,
      label: 'Backups',
      icon: Database,
      description: 'Manage backup and restore operations'
    },
    {
      id: 'settings' as const,
      label: 'Settings',
      icon: Settings,
      description: 'Configure plugin options'
    },
    {
      id: 'help' as const,
      label: 'Help',
      icon: HelpCircle,
      description: 'Documentation and guides'
    }
  ];

  return (
    <aside className="wcfdr-w-64 wcfdr-bg-white wcfdr-border-r wcfdr-border-gray-200 wcfdr-min-h-screen">
      <div className="wcfdr-p-6">
        <div className="wcfdr-mb-8">
          <h2 className="wcfdr-text-lg wcfdr-font-semibold wcfdr-text-gray-900 wcfdr-mb-2">
            Navigation
          </h2>
          <p className="wcfdr-text-sm wcfdr-text-gray-600">
            Choose your operation type
          </p>
        </div>

        <nav className="wcfdr-space-y-2">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            
            return (
              <button
                key={tab.id}
                onClick={() => onTabChange(tab.id)}
                className={`wcfdr-w-full wcfdr-text-left wcfdr-p-3 wcfdr-rounded-lg wcfdr-transition-all wcfdr-duration-200 ${
                  isActive
                    ? 'wcfdr-bg-blue-50 wcfdr-border wcfdr-border-blue-200 wcfdr-text-blue-700'
                    : 'wcfdr-text-gray-700 hover:wcfdr-bg-gray-50 hover:wcfdr-text-gray-900'
                }`}
              >
                <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-3">
                  <Icon className={`wcfdr-h-5 wcfdr-w-5 ${
                    isActive ? 'wcfdr-text-blue-600' : 'wcfdr-text-gray-500'
                  }`} />
                  <div className="wcfdr-flex-1">
                    <div className="wcfdr-font-medium">{tab.label}</div>
                    <div className="wcfdr-text-xs wcfdr-text-gray-500 wcfdr-mt-1">
                      {tab.description}
                    </div>
                  </div>
                </div>
              </button>
            );
          })}
        </nav>

        {/* Plugin Status */}
        <div className="wcfdr-mt-8 wcfdr-pt-6 wcfdr-border-t wcfdr-border-gray-200">
          <div className="wcfdr-bg-gray-50 wcfdr-rounded-lg wcfdr-p-4">
            <div className="wcfdr-flex wcfdr-items-center wcfdr-space-x-2 wcfdr-mb-3">
              <Activity className="wcfdr-h-4 wcfdr-w-4 wcfdr-text-green-600" />
              <span className="wcfdr-text-sm wcfdr-font-medium wcfdr-text-gray-900">
                Plugin Status
              </span>
            </div>
            
            <div className="wcfdr-space-y-2 wcfdr-text-xs wcfdr-text-gray-600">
              <div className="wcfdr-flex wcfdr-justify-between">
                <span>Backend:</span>
                <span className="wcfdr-text-green-600 wcfdr-font-medium">Connected</span>
              </div>
              <div className="wcfdr-flex wcfdr-justify-between">
                <span>Database:</span>
                <span className="wcfdr-text-green-600 wcfdr-font-medium">Ready</span>
              </div>
              <div className="wcfdr-flex wcfdr-justify-between">
                <span>Permissions:</span>
                <span className="wcfdr-text-green-600 wcfdr-font-medium">Admin</span>
              </div>
            </div>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="wcfdr-mt-6">
          <button 
            onClick={onSettingsClick}
            className="wcfdr-w-full wcfdr-inline-flex wcfdr-items-center wcfdr-justify-center wcfdr-px-4 wcfdr-py-2 wcfdr-border wcfdr-border-gray-300 wcfdr-shadow-sm wcfdr-text-sm wcfdr-font-medium wcfdr-rounded-md wcfdr-text-gray-700 wcfdr-bg-white hover:wcfdr-bg-gray-50"
          >
            <Settings className="wcfdr-h-4 wcfdr-w-4 wcfdr-mr-2" />
            Plugin Settings
          </button>
        </div>
      </div>
    </aside>
  );
};
