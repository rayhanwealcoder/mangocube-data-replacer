import React from 'react';

interface LoadingSpinnerProps {
  size?: 'small' | 'medium' | 'large';
  className?: string;
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({ 
  size = 'medium', 
  className = '' 
}) => {
  const sizeClasses = {
    small: 'wcfdr-h-4 wcfdr-w-4',
    medium: 'wcfdr-h-8 wcfdr-w-8',
    large: 'wcfdr-h-12 wcfdr-w-12'
  };

  return (
    <div className={`wcfdr-flex wcfdr-items-center wcfdr-justify-center wcfdr-p-8 ${className}`}>
      <div className={`wcfdr-animate-spin wcfdr-rounded-full wcfdr-border-b-2 wcfdr-border-blue-600 ${sizeClasses[size]}`}></div>
    </div>
  );
};
